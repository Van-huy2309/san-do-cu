<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client LLM: Gemini, Groq, OpenRouter (miễn phí) và OpenAI-compatible.
 * provider=auto → thử lần lượt các provider có key; lỗi/hết quota thì sang provider kế.
 * Không có key nào → null (Care/Ops dùng template cục bộ).
 */
class LlmClient
{
    private const ORDER = ['gemini', 'groq', 'openrouter', 'openai'];

    private const OPENAI_COMPATIBLE = ['groq', 'openrouter', 'openai'];

    public function enabled(): bool
    {
        return $this->providers() !== [];
    }

    public function provider(): string
    {
        return $this->providers()[0] ?? 'off';
    }

    /** @return list<string> */
    public function providers(): array
    {
        $p = strtolower((string) config('ai.llm.provider', 'auto'));
        if ($p === 'off') {
            return [];
        }

        $candidates = $p === 'auto' ? self::ORDER : [$p];

        return array_values(array_filter(
            $candidates,
            fn ($name) => in_array($name, self::ORDER, true) && $this->apiKey($name) !== ''
        ));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, array $opts = []): ?string
    {
        foreach ($this->providers() as $provider) {
            try {
                $text = $provider === 'gemini'
                    ? $this->chatGemini($messages, $opts)
                    : $this->chatOpenAiCompatible($provider, $messages, $opts);

                $text = trim((string) $text);
                if ($text !== '') {
                    return $text;
                }
            } catch (Throwable $e) {
                Log::warning('Relic LLM failed', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function apiKey(string $provider): string
    {
        return trim((string) config("services.{$provider}.key", ''));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function chatOpenAiCompatible(string $provider, array $messages, array $opts): ?string
    {
        $defaults = [
            'openai' => ['https://api.openai.com/v1', 'gpt-4o-mini'],
            'groq' => ['https://api.groq.com/openai/v1', 'openai/gpt-oss-120b'],
            'openrouter' => ['https://openrouter.ai/api/v1', 'meta-llama/llama-3.3-70b-instruct:free'],
        ][$provider];

        $base = rtrim((string) config("services.{$provider}.base", $defaults[0]), '/');
        $models = array_values(array_unique(array_filter([
            (string) (config("services.{$provider}.model") ?: $defaults[1]),
            (string) config("services.{$provider}.fallback_model", ''),
        ])));

        foreach ($models as $model) {
            $text = $this->postOpenAiCompatible($provider, $base, $model, $messages, $opts);
            if ($text !== null && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function postOpenAiCompatible(string $provider, string $base, string $model, array $messages, array $opts): ?string
    {
        $timeout = (int) config('ai.llm.timeout', 25);

        $req = Http::timeout($timeout)
            ->withToken($this->apiKey($provider))
            ->acceptJson();

        if ($provider === 'openrouter') {
            $req = $req->withHeaders([
                'HTTP-Referer' => (string) config('app.url', 'http://localhost'),
                'X-Title' => 'Relic Care',
            ]);
        }

        $body = [
            'model' => $model,
            'temperature' => (float) ($opts['temperature'] ?? config('ai.llm.temperature', 0.4)),
            'max_tokens' => (int) ($opts['max_tokens'] ?? config('ai.llm.max_tokens', 700)),
            'messages' => $messages,
        ];
        // gpt-oss là model suy luận: token suy luận tính vào max_tokens.
        if (str_contains($model, 'gpt-oss')) {
            $body['reasoning_effort'] = 'low';
            if ($provider === 'groq') {
                $body['include_reasoning'] = false;
            }
        }

        $res = $req->post($base.'/chat/completions', $body);

        // Hết hạn mức token/phút: chờ ngắn rồi thử lại một lần.
        if ($res->status() === 429) {
            $wait = (float) ($res->header('retry-after') ?: 0);
            if (preg_match('/try again in ([\d.]+)s/', $res->body(), $m)) {
                $wait = max($wait, (float) $m[1]);
            }
            if ($wait > 0 && $wait <= 3) {
                usleep((int) ceil($wait * 1_000_000));
                $res = $req->post($base.'/chat/completions', $body);
            }
        }

        if (! $res->successful()) {
            Log::warning('LLM HTTP error', ['provider' => $provider, 'model' => $model, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);

            return null;
        }

        return data_get($res->json(), 'choices.0.message.content');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function chatGemini(array $messages, array $opts): ?string
    {
        $model = (string) (config('services.gemini.model') ?: 'gemini-2.0-flash');
        $timeout = (int) config('ai.llm.timeout', 25);
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';

        $system = '';
        $contents = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$content;
                continue;
            }
            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $last = count($contents) - 1;
            // Gemini yêu cầu xen kẽ user/model.
            if ($last >= 0 && $contents[$last]['role'] === $geminiRole) {
                $contents[$last]['parts'][0]['text'] .= "\n\n".$content;
                continue;
            }
            $contents[] = ['role' => $geminiRole, 'parts' => [['text' => $content]]];
        }

        if ($contents === []) {
            return null;
        }
        if ($contents[0]['role'] !== 'user') {
            array_unshift($contents, ['role' => 'user', 'parts' => [['text' => '(bắt đầu hội thoại)']]]);
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($opts['temperature'] ?? config('ai.llm.temperature', 0.4)),
                'maxOutputTokens' => (int) ($opts['max_tokens'] ?? config('ai.llm.max_tokens', 400)),
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $res = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $this->apiKey('gemini')])
            ->post($url, $payload);

        if (! $res->successful()) {
            Log::warning('Gemini HTTP error', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);

            return null;
        }

        $parts = data_get($res->json(), 'candidates.0.content.parts', []);
        if (! is_array($parts)) {
            return null;
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        return $text;
    }
}
