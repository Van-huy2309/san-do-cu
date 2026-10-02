<?php

namespace Tests\Feature;

use App\Mcp\RelicMcpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelicMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialize_and_tools_list_follow_json_rpc(): void
    {
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'relic-web', 'version' => '1.0.0'],
            ],
        ])->assertOk()->json();

        $this->assertSame('2.0', $init['jsonrpc']);
        $this->assertSame(1, $init['id']);
        $this->assertSame(RelicMcpServer::PROTOCOL, $init['result']['protocolVersion']);
        $this->assertSame('relic', $init['result']['serverInfo']['name']);

        $this->actingAs($user)->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ])->assertStatus(202);

        $tools = $this->actingAs($user)->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');

        $this->assertSame(['relic.care'], array_column($tools, 'name'));
    }

    public function test_care_tool_call_returns_mcp_content(): void
    {
        $result = $this->actingAs(User::factory()->create())->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'relic.care',
                'arguments' => ['message' => 'Escrow MoMo giữ tiền thế nào?'],
            ],
        ])->assertOk()->json('result');

        $this->assertFalse($result['isError']);
        $this->assertSame('text', $result['content'][0]['type']);
        $this->assertStringContainsString('MoMo', $result['content'][0]['text']);
        $this->assertStringContainsString('giữ tiền', $result['content'][0]['text']);
        $this->assertIsArray($result['structuredContent']['links']);
    }

    public function test_customer_cannot_call_ops_tool(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'relic.ops',
                'arguments' => ['message' => 'bao nhiêu user?'],
            ],
        ])->assertOk()
            ->assertJsonPath('error.code', -32602);
    }

    public function test_admin_can_list_ops_tool(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $names = $this->actingAs($admin)->postJson(route('mcp'), [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');

        $this->assertEqualsCanonicalizing(['relic.care', 'relic.ops'], array_column($names, 'name'));
    }
}
