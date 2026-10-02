<?php

namespace App\Mcp;

use App\Models\User;
use App\Services\RelicAi;
use Throwable;

/**
 * Máy chủ MCP (JSON-RPC 2.0) cho Relic Care và Relic Ops.
 * Giao thức: https://modelcontextprotocol.io — phiên bản 2025-03-26.
 */
class RelicMcpServer
{
    public const PROTOCOL = '2025-03-26';

    public function __construct(private RelicAi $ai) {}

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null  null = notification, không trả body
     */
    public function dispatch(User $user, array $message): ?array
    {
        $id = $message['id'] ?? null;
        $isNotification = ! array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($message['method'] ?? null) || $message['method'] === '') {
            return $this->error(is_int($id) || is_string($id) ? $id : null, -32600, 'Invalid Request');
        }

        $method = $message['method'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'notifications/initialized', 'notifications/cancelled' => null,
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->toolsFor($user)],
                'tools/call' => $this->callTool($user, $params),
                default => throw new McpException(-32601, 'Method not found'),
            };
        } catch (McpException $e) {
            if ($isNotification) {
                return null;
            }

            return $this->error($id, $e->rpcCode, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            if ($isNotification) {
                return null;
            }

            return $this->error($id, -32603, 'Internal error');
        }

        if ($isNotification || $result === null) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? '');
        $supported = ['2024-11-05', '2025-03-26', '2025-06-18'];
        if ($requested !== '' && ! in_array($requested, $supported, true)) {
            throw new McpException(-32602, 'Unsupported protocol version');
        }

        return [
            'protocolVersion' => self::PROTOCOL,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'relic',
                'version' => '1.0.0',
            ],
            'instructions' => 'Dùng tools/call với relic.care (người mua/bán) hoặc relic.ops (admin). Đối số bắt buộc: message.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolsFor(User $user): array
    {
        $tools = [$this->tool(
            'relic.care',
            'Tư vấn mua, bán, đơn hàng, vận chuyển và escrow trên Relic.',
            (int) config('ai.care.max_message', 2000),
        )];

        if ($user->isAdmin()) {
            $tools[] = $this->tool(
                'relic.ops',
                'Phụ tá vận hành: user, đơn, KYC, tài chính, vận đơn.',
                (int) config('ai.ops.max_message', 800),
            );
        }

        return $tools;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, int $max): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => $max,
                        'description' => 'Câu người dùng gửi cho AI.',
                    ],
                ],
                'required' => ['message'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(User $user, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $message = isset($arguments['message']) ? trim((string) $arguments['message']) : '';

        if (! is_string($name) || $name === '') {
            throw new McpException(-32602, 'Missing tool name');
        }

        $allowed = collect($this->toolsFor($user))->pluck('name')->all();
        if (! in_array($name, $allowed, true)) {
            throw new McpException(-32602, 'Unknown or forbidden tool');
        }

        $max = $name === 'relic.ops'
            ? (int) config('ai.ops.max_message', 800)
            : (int) config('ai.care.max_message', 2000);

        if ($message === '' || mb_strlen($message) > $max) {
            throw new McpException(-32602, 'Invalid message');
        }

        try {
            $out = $name === 'relic.ops'
                ? $this->ai->ops($user, $message)
                : $this->ai->care($user, $message);
        } catch (Throwable $e) {
            report($e);

            return [
                'content' => [[
                    'type' => 'text',
                    'text' => $name === 'relic.ops'
                        ? 'Ops vừa lỗi nội bộ. Thử “bao nhiêu user?” hoặc “help”.'
                        : 'Care vừa lỗi nội bộ. Thử hỏi lại ngắn hơn nhé.',
                ]],
                'isError' => true,
            ];
        }

        return [
            'content' => [[
                'type' => 'text',
                'text' => (string) ($out['reply'] ?? ''),
            ]],
            'structuredContent' => [
                'links' => array_values($out['links'] ?? []),
                'products' => array_values($out['products'] ?? []),
                'open' => $out['open'] ?? null,
            ],
            'isError' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function error(int|string|null $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
