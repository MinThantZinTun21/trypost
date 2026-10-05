<?php

declare(strict_types=1);

use App\Console\Commands\ProcessScheduledPosts;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\FacebookPublisher;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('queue.default', 'database');
    config()->set('cache.default', 'database');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $socialAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $this->postPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $socialAccount->id,
        'enabled' => true,
    ]);
});

test('a due scheduled post publishes end to end through the database queue', function () {
    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);
    $this->app->instance(FacebookPublisher::class, $publisher);

    $this->artisan(ProcessScheduledPosts::class)->assertSuccessful();

    expect(DB::table('jobs')->pluck('queue')->all())->toBe(['default']);

    $queues = implode(',', ['default', ...Platform::allQueues()]);
    $this->artisan('queue:work', ['--queue' => $queues, '--stop-when-empty' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Published)
        ->and($this->post->fresh()->status)->toBe(PostStatus::Published);
});
