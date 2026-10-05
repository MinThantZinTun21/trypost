<?php

declare(strict_types=1);

use App\Actions\Post\CreatePost;
use App\Enums\Post\CreatedVia;
use App\Models\User;
use App\Models\Workspace;

test('execute persists created_via for each entry point', function (CreatedVia $createdVia) {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    $post = CreatePost::execute($workspace, $user, [
        'content' => 'Hello world',
        'created_via' => $createdVia,
    ]);

    expect($post->fresh()->created_via)->toBe($createdVia);
})->with([
    'web' => CreatedVia::Web,
]);

test('execute leaves created_via null when omitted', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);

    $post = CreatePost::execute($workspace, $user, [
        'content' => 'Hello world',
    ]);

    expect($post->fresh()->created_via)->toBeNull();
});
