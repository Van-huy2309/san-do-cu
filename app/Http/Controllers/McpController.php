<?php

namespace App\Http\Controllers;

use App\Mcp\RelicMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class McpController extends Controller
{
    public function handle(Request $request, RelicMcpServer $mcp): JsonResponse|Response
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json($mcp->error(null, -32700, 'Parse error'), 400);
        }

        if (array_is_list($payload)) {
            $batch = [];
            foreach ($payload as $message) {
                if (! is_array($message)) {
                    $batch[] = $mcp->error(null, -32600, 'Invalid Request');

                    continue;
                }
                $one = $mcp->dispatch($request->user(), $message);
                if ($one !== null) {
                    $this->remember($message, $one);
                    $batch[] = $one;
                }
            }

            return $batch === []
                ? response()->noContent(202)
                : response()->json($batch);
        }

        $response = $mcp->dispatch($request->user(), $payload);
        if ($response === null) {
            return response()->noContent(202);
        }

        $this->remember($payload, $response);
        $status = isset($response['error']) && ($response['error']['code'] ?? 0) === -32700 ? 400 : 200;

        return response()->json($response, $status);
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $response
     */
    private function remember(array $message, array $response): void
    {
        if (($message['method'] ?? '') !== 'tools/call' || isset($response['error'])) {
            return;
        }

        $result = $response['result'] ?? null;
        if (! is_array($result) || ($result['isError'] ?? false)) {
            return;
        }

        $name = $message['params']['name'] ?? '';
        $channel = $name === 'relic.ops' ? 'ops' : 'care';
        $text = trim((string) ($message['params']['arguments']['message'] ?? ''));
        $reply = '';
        foreach ($result['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $reply = (string) ($block['text'] ?? '');
                break;
            }
        }
        $extra = is_array($result['structuredContent'] ?? null) ? $result['structuredContent'] : [];

        $key = 'relic.ai.'.$channel.'.log';
        $log = session($key, []);
        if (! is_array($log)) {
            $log = [];
        }

        $log[] = ['who' => 'me', 'text' => $text, 'links' => [], 'products' => []];
        $log[] = [
            'who' => 'bot',
            'text' => $reply,
            'links' => array_values($extra['links'] ?? []),
            'products' => array_values($extra['products'] ?? []),
        ];
        session([$key => array_slice($log, -50)]);

        if (! empty($extra['open'])) {
            session(['relic.ai.'.$channel.'.open' => true]);
        }
    }
}
