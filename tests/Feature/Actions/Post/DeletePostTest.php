<?php

declare(strict_types=1);

use App\Actions\Post\DeletePost;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;

test('execute deletes the post', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    DeletePost::execute($post);

    expect(Post::find($post->id))->toBeNull();
});
