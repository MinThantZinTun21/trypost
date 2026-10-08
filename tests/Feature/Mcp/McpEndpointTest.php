<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('trypost.mcp.token', 'mcp-test-token');

    $owner = User::factory()->create();
    Workspace::factory()->create(['user_id' => $owner->id]);
});

function mcpInitialize(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'claude-code', 'version' => '1.0'],
        ],
    ];
}

test('the mcp endpoint is off when no token is configured', function () {
    config()->set('trypost.mcp.token', '');

    $this->postJson(route('mcp'), mcpInitialize(), ['Authorization' => 'Bearer mcp-test-token'])->assertNotFound();
});

test('the mcp endpoint rejects a missing or wrong token', function (array $headers) {
    $this->postJson(route('mcp'), mcpInitialize(), $headers)->assertUnauthorized();
})->with([
    'no token' => [[]],
    'wrong token' => [['Authorization' => 'Bearer nope']],
]);

test('the mcp endpoint answers the handshake with the right token', function () {
    $this->postJson(route('mcp'), mcpInitialize(), ['Authorization' => 'Bearer mcp-test-token'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'TryPost');
});

test('tools act as the owner', function () {
    $this->postJson(route('mcp'), [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'list_social_accounts', 'arguments' => (object) []],
    ], ['Authorization' => 'Bearer mcp-test-token'])
        ->assertOk()
        ->assertJsonPath('result.isError', false);
});
