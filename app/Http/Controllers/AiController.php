<?php

namespace App\Http\Controllers;

use App\Services\RelicAi;
use Illuminate\Http\Request;
use Throwable;

class AiController extends Controller
{
    public function care(Request $request, RelicAi $ai)
    {
        $data = $request->validate(['message' => 'required|string|max:'.(int) config('ai.care.max_message', 800)]);

        try {
            $out = $ai->care($request->user(), $data['message']);

            return response()->json($this->remember('care', $data['message'], $out));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'reply' => 'Care vừa lỗi nội bộ. Thử hỏi lại ngắn hơn nhé.',
                'links' => [],
                'products' => [],
            ]);
        }
    }

    public function ops(Request $request, RelicAi $ai)
    {
        $data = $request->validate(['message' => 'required|string|max:'.(int) config('ai.ops.max_message', 800)]);

        try {
            $out = $ai->ops($request->user(), $data['message']);

            return response()->json($this->remember('ops', $data['message'], $out));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'reply' => 'Ops vừa lỗi nội bộ. Thử “bao nhiêu user?” hoặc “help”.',
                'links' => [['label' => 'Dashboard', 'url' => route('admin.dashboard')]],
                'products' => [],
            ]);
        }
    }

    /** Lưu hội thoại vào session PHP để còn sau khi đổi trang admin. */
    private function remember(string $channel, string $message, array $out): array
    {
        $key = 'relic.ai.'.$channel.'.log';
        $log = session($key, []);
        if (! is_array($log)) {
            $log = [];
        }

        $log[] = [
            'who' => 'me',
            'text' => $message,
            'links' => [],
            'products' => [],
        ];
        $log[] = [
            'who' => 'bot',
            'text' => (string) ($out['reply'] ?? ''),
            'links' => array_values($out['links'] ?? []),
            'products' => array_values($out['products'] ?? []),
        ];
        session([$key => array_slice($log, -50)]);

        if (! empty($out['open'])) {
            session(['relic.ai.'.$channel.'.open' => true]);
        }

        return $out;
    }
}
