<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('signature, label, asset library, stock media and webhook endpoints are gone', function (string $method, string $uri) {
    $this->actingAs($this->owner)->json($method, $uri)->assertNotFound();
})->with([
    'signatures' => ['GET', '/signatures'],
    'signatures store' => ['POST', '/signatures'],
    'labels' => ['GET', '/labels'],
    'labels store' => ['POST', '/labels'],
    'assets' => ['GET', '/assets'],
    'assets search' => ['GET', '/assets/search'],
    'assets store' => ['POST', '/assets'],
    'assets from url' => ['POST', '/assets/from-url'],
    'unsplash search' => ['GET', '/assets/unsplash/search'],
    'unsplash trending' => ['GET', '/assets/unsplash/trending'],
    'giphy search' => ['GET', '/assets/giphy/search'],
    'giphy trending' => ['GET', '/assets/giphy/trending'],
    'webhooks' => ['GET', '/webhooks'],
    'webhooks store' => ['POST', '/webhooks'],
]);

test('the removed tables are dropped', function (string $table) {
    expect(Schema::hasTable($table))->toBeFalse();
})->with(['workspace_signatures', 'workspace_labels', 'post_workspace_label', 'webhooks', 'webhook_logs']);

test('webhook log pruning is no longer scheduled', function () {
    $this->artisan('schedule:list')
        ->doesntExpectOutputToContain('webhook')
        ->assertSuccessful();
});

test('a post is duplicated without labels', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'content' => 'Copy me',
        'status' => PostStatus::Published,
    ]);

    $this->actingAs($this->owner)
        ->post(route('app.posts.duplicate', $post))
        ->assertRedirect();

    $copy = Post::query()->whereKeyNot($post->id)->sole();

    expect($copy->content)->toBe('Copy me')
        ->and($copy->status)->toBe(PostStatus::Draft);
});

test('the post editor no longer receives labels or signatures', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.posts.edit', $post))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('posts/Edit')
            ->missing('labels')
            ->missing('signatures')
        );
});
