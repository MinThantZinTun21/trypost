<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Support\Facades\DB;

test('deletes every Draft with its Post platforms and keeps every other Post', function () {
    $draft = Post::factory()->scheduled()->create();
    DB::table('posts')->where('id', $draft->id)->update(['status' => 'draft']);
    $draftPlatform = PostPlatform::factory()->create(['post_id' => $draft->id]);
    $scheduled = Post::factory()->scheduled()->create();
    $scheduledPlatform = PostPlatform::factory()->create(['post_id' => $scheduled->id]);
    $published = Post::factory()->published()->create();
    $failed = Post::factory()->failed()->create();

    $migration = require database_path('migrations/2026_10_18_000000_delete_drafts.php');
    $migration->up();

    expect(Post::query()->pluck('id')->all())->toEqualCanonicalizing([$scheduled->id, $published->id, $failed->id])
        ->and(PostPlatform::query()->pluck('id')->all())->toBe([$scheduledPlatform->id])
        ->and(PostPlatform::query()->whereKey($draftPlatform->id)->exists())->toBeFalse();
});

test('a Post saved without a status is Scheduled', function () {
    $post = Post::factory()->scheduled()->create();
    DB::table('posts')->insert([
        ...collect($post->getAttributes())->except(['id', 'status', 'media'])->all(),
        'id' => fake()->uuid(),
        'media' => '[]',
    ]);

    expect(DB::table('posts')->where('id', '!=', $post->id)->value('status'))->toBe('scheduled');
});
