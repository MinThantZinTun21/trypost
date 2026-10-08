<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\FacebookPublisher;
use App\Support\CronToken;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config()->set('queue.default', 'database');
    config()->set('cache.default', 'database');
    config()->set('trypost.cron.secret', 'cron-test-secret');

    $user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $socialAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);

    $this->duePost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $this->duePlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->duePost->id,
        'social_account_id' => $socialAccount->id,
        'enabled' => true,
    ]);

    $this->upcomingPost = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addHours(3)->startOfMinute(),
    ]);
});

function fakeFacebookPublishing(): void
{
    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);
    app()->instance(FacebookPublisher::class, $publisher);
}

test('the endpoint is disabled when no cron secret is configured', function () {
    config()->set('trypost.cron.secret', null);

    $this->getJson(route('cron.run'))->assertNotFound();

    expect($this->duePost->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('the endpoint rejects a missing or wrong token', function (array $headers, array $query) {
    $this->getJson(route('cron.run', $query), $headers)->assertStatus(Response::HTTP_UNAUTHORIZED);

    expect($this->duePost->fresh()->status)->toBe(PostStatus::Scheduled);
})->with([
    'no token' => [[], []],
    'wrong bearer' => [['Authorization' => 'Bearer nope'], []],
    'wrong query token' => [[], ['token' => 'nope']],
]);

test('an unexpired token signed with the secret is accepted', function () {
    fakeFacebookPublishing();
    $token = CronToken::issue('cron-test-secret', now()->addDays(30));

    $this->getJson(route('cron.run'), ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($this->duePost->fresh()->status)->toBe(PostStatus::Published);
});

test('an expired, tampered or foreign token is rejected', function (Closure $token) {
    $this->getJson(route('cron.run', ['token' => $token()]))->assertUnauthorized();

    expect($this->duePost->fresh()->status)->toBe(PostStatus::Scheduled);
})->with([
    'expired' => [fn () => CronToken::issue('cron-test-secret', now()->subSecond())],
    'extended expiry' => [fn () => preg_replace('/^\d+/', (string) now()->addYear()->getTimestamp(), CronToken::issue('cron-test-secret', now()->addDay()))],
    'signed with another secret' => [fn () => CronToken::issue('other-secret', now()->addDay())],
    'no signature' => [fn () => (string) now()->addDay()->getTimestamp()],
]);

test('a call publishes due posts and reports the next scheduled post', function () {
    fakeFacebookPublishing();

    $this->getJson(route('cron.run'), ['Authorization' => 'Bearer cron-test-secret'])
        ->assertOk()
        ->assertJsonPath('jobs_remaining', 0)
        ->assertJsonPath('next_scheduled_post.id', $this->upcomingPost->id)
        ->assertJsonPath('next_scheduled_post.scheduled_at', $this->upcomingPost->scheduled_at->toIso8601String());

    expect($this->duePlatform->fresh()->status)->toBe(PlatformStatus::Published)
        ->and($this->duePost->fresh()->status)->toBe(PostStatus::Published)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('the token can be passed as a query parameter', function () {
    fakeFacebookPublishing();

    $this->getJson(route('cron.run', ['token' => 'cron-test-secret']))->assertOk();

    expect($this->duePost->fresh()->status)->toBe(PostStatus::Published);
});

test('the next scheduled post is null when nothing is upcoming', function () {
    fakeFacebookPublishing();
    $this->upcomingPost->delete();

    $this->getJson(route('cron.run'), ['Authorization' => 'Bearer cron-test-secret'])
        ->assertOk()
        ->assertJsonPath('next_scheduled_post', null);
});

test('a cron caller that hangs up early does not stop the run', function () {
    $previous = ignore_user_abort(false);

    $this->getJson(route('cron.run'), ['Authorization' => 'Bearer cron-test-secret'])->assertOk();

    expect(ignore_user_abort())->toBe(1);

    ignore_user_abort((bool) $previous);
});

test('a wrong token does not switch on ignore user abort', function () {
    $previous = ignore_user_abort(false);

    $this->getJson(route('cron.run'), ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

    expect(ignore_user_abort())->toBe(0);

    ignore_user_abort((bool) $previous);
});
