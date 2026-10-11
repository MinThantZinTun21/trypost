<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\FacebookPublisher;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;

test('every scheduled task is a callback, not an artisan subprocess', function () {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(CallbackEvent::class);
    }
});

test('due posts are processed every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'posts:process-scheduled');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});

test('a schedule run publishes a due post', function () {
    $account = SocialAccount::factory()->facebook()->create();
    $post = Post::factory()->create([
        'workspace_id' => $account->workspace_id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $platform = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);
    app()->instance(FacebookPublisher::class, $publisher);

    $this->artisan('schedule:run')->assertSuccessful();

    expect($platform->fresh()->status)->toBe(PlatformStatus::Published)
        ->and($post->fresh()->status)->toBe(PostStatus::Published);
});
