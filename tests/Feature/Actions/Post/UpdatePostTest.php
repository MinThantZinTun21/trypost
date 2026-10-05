<?php

declare(strict_types=1);

use App\Actions\Post\UpdatePost;
use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

test('disabled youtube metadata does not block scheduling', function (bool $resubmitPlatforms) {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    $platform = PostPlatform::factory()->youtube()->create([
        'post_id' => $post->id,
        'social_account_id' => SocialAccount::factory()->youtube()->create([
            'workspace_id' => $workspace->id,
        ])->id,
        'enabled' => false,
        'meta' => ['description' => str_repeat('a', 5001)],
    ]);
    $data = ['status' => PostStatus::Scheduled->value];

    if ($resubmitPlatforms) {
        $data['platforms'] = [];
    }

    UpdatePost::execute($workspace, $post, $data);

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and($platform->fresh()->enabled)->toBeFalse();
})->with([
    'omitted platforms' => [false],
    'deselected platforms' => [true],
]);
