<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $workspace->id]);
});

test('content idea and content library pages are gone', function (string $method, string $uri) {
    $this->actingAs($this->owner)->json($method, $uri)->assertNotFound();
})->with([
    'ideas' => ['GET', '/ideas'],
    'idea' => ['GET', '/ideas/'.fake()->uuid()],
    'store idea' => ['POST', '/ideas'],
    'library' => ['GET', '/library'],
    'store library item' => ['POST', '/library'],
    'schedule library item' => ['POST', '/library/'.fake()->uuid().'/schedule'],
]);

test('their tables and the post link are dropped', function () {
    expect(Schema::hasTable('content_ideas'))->toBeFalse()
        ->and(Schema::hasTable('library_items'))->toBeFalse()
        ->and(Schema::hasColumn('posts', 'library_item_id'))->toBeFalse();
});

test('their models are gone', function () {
    expect(class_exists('App\\Models\\ContentIdea'))->toBeFalse()
        ->and(class_exists('App\\Models\\LibraryItem'))->toBeFalse();
});

test('the assistant has no content idea or library tools', function () {
    config()->set('trypost.mcp.token', 'mcp-test-token');

    $names = collect($this->postJson(route('mcp'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Authorization' => 'Bearer mcp-test-token'])->assertOk()->json('result.tools'))->pluck('name');

    expect($names)->toContain('create_post', 'delete_post')
        ->and($names->filter(fn (string $name): bool => str_contains($name, 'idea') || str_contains($name, 'library')))->toBeEmpty();
});
