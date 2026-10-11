<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\FacebookPublisher;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

test('every scheduled task is a callback, not an artisan subprocess', function () {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(CallbackEvent::class);
    }
});

test('due posts are processed every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === 'posts:process-scheduled');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});

test('a schedule run queues a due post that the worker publishes', function () {
    config()->set('queue.default', 'database');
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
    $this->artisan('queue:work', [
        '--queue' => implode(',', ['default', ...Platform::allQueues()]),
        '--stop-when-empty' => true,
    ])->assertSuccessful();

    expect($platform->fresh()->status)->toBe(PlatformStatus::Published)
        ->and($post->fresh()->status)->toBe(PostStatus::Published)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});
