<?php

declare(strict_types=1);

use App\Enums\Notification\Type;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Enums\TikTok\PrivacyLevel;
use App\Enums\UserWorkspace\Role;
use App\Events\PostPlatformStatusUpdated;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\FacebookPublishException;
use App\Exceptions\TokenExpiredException;
use App\Jobs\PublishToSocialPlatform;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\ConnectionVerifier;
use App\Services\Social\FacebookPublisher;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->socialAccount = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->post = Post::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $this->postPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'enabled' => true,
    ]);
});

test('publish to social platform marks platform as publishing', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Event::assertDispatched(PostPlatformStatusUpdated::class);
});

test('publish to social platform marks platform as published on success', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Published);
    expect($this->postPlatform->platform_post_id)->toBe('post-123');
    expect($this->postPlatform->platform_url)->toBe('https://facebook.com/post/123');
});

test('publish to social platform marks platform as failed on error', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API Error'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe('An unexpected error occurred while publishing. Please try again.');
});

test('publish keeps the vetted user message from a publish exception', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new FacebookPublishException(
        userMessage: 'Facebook rejected this post.',
        category: ErrorCategory::ContentPolicy,
    ));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe('Facebook rejected this post.');
});

test('publish reports caught publish exceptions so the exception handler sees them', function (FacebookPublishException $exception) {
    Event::fake();
    Exceptions::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow($exception);

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(FacebookPublishException::class);
    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed)
        ->and($this->postPlatform->error_message)->toBe($exception->userMessage);
})->with([
    'server error' => fn () => new FacebookPublishException(
        userMessage: 'Facebook could not process the media.',
        category: ErrorCategory::ServerError,
        platformErrorCode: 'media-processing-timeout',
        rawResponse: '{"status":"ERROR"}',
    ),
    'content policy' => fn () => new FacebookPublishException(
        userMessage: 'Facebook rejected this post.',
        category: ErrorCategory::ContentPolicy,
    ),
]);

test('publish reports unexpected errors so the exception handler sees them', function () {
    Event::fake();
    Exceptions::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TypeError('API Error'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(TypeError::class);
    $this->postPlatform->refresh();
    expect($this->postPlatform->error_message)->toBe('An unexpected error occurred while publishing. Please try again.');
});

test('publish reports token expiry so the exception handler sees it', function () {
    Event::fake();
    Exceptions::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '401'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(TokenExpiredException::class);
});

test('publish reports a failed token refresh once', function () {
    Event::fake();
    Exceptions::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '190'));
    $this->app->instance(FacebookPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->andThrow(new TokenExpiredException('Refresh failed'));
    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (TokenExpiredException $e): bool => $e->getMessage() === 'Refresh failed');
    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Failed);
});

test('publish does not report a platform-unavailable retry', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Exceptions::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertNothingReported();
    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Retrying);
});

test('publish log includes media so logs can tell a CDN miss from an API rejection', function () {
    Exceptions::fake();

    $this->post->update([
        'media' => [[
            'url' => 'https://cdn.trypost.it/media/2026-01/clip.mp4',
            'mime_type' => 'video/mp4',
            'size' => 4_194_304,
            'path' => 'media/2026-01/clip.mp4',
            'original_filename' => 'clip.mp4',
        ]],
    ]);

    $logs = [];
    Log::listen(function (MessageLogged $event) use (&$logs): void {
        $logs[] = $event;
    });

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new FacebookPublishException(
            userMessage: 'Facebook media processing failed',
            category: ErrorCategory::ServerError,
            rawResponse: '{"status":"ERROR","detail":"download failed"}',
        )
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    $entry = collect($logs)->first(
        fn (MessageLogged $event): bool => $event->message === 'Social publish failed'
    );

    expect($entry)->not->toBeNull()
        ->and($entry->level)->toBe('error')
        ->and(data_get($entry->context, 'platform'))->toBe('facebook')
        ->and(data_get($entry->context, 'media.0.url'))->toBe('https://cdn.trypost.it/media/2026-01/clip.mp4')
        ->and(data_get($entry->context, 'media.0.mime_type'))->toBe('video/mp4')
        ->and(data_get($entry->context, 'media.0.size'))->toBe(4_194_304)
        ->and(data_get($entry->context, 'media.0.type'))->toBe('video')
        ->and(data_get($entry->context, 'content_type'))->toBe('facebook_post')
        ->and(data_get($entry->context, 'raw_response'))->toBe('{"status":"ERROR","detail":"download failed"}');
});

test('publish reports when platform-unavailable retries are exhausted', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Exceptions::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('TikTok is still processing publish_id pub_stuck', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    $this->postPlatform->update([
        'error_context' => ['retry_count' => PublishToSocialPlatform::MAX_PLATFORM_UNAVAILABLE_RETRIES],
    ]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(PlatformUnavailableException::class);
    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Failed);
});

test('publish never leaks a raw internal error to the failure record (and the email)', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TypeError(
        'X::getMediaCategory(): Argument #1 ($mimeType) must be of type string, null given, called in /home/forge/app.trypost.it/releases/72198060/app/Services/Social/XPublisher.php on line 130'
    ));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe('An unexpected error occurred while publishing. Please try again.')
        ->and($this->postPlatform->error_message)->not->toContain('/home/forge')
        ->and($this->postPlatform->error_message)->not->toContain('getMediaCategory');
});

test('the job-failed hook also genericizes a raw internal error', function () {
    Event::fake();

    (new PublishToSocialPlatform($this->postPlatform))->failed(new TypeError(
        'boom in /home/forge/app.trypost.it/releases/72198060/app/Services/Social/XPublisher.php on line 130'
    ));

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed)
        ->and($this->postPlatform->error_message)->toBe('An unexpected error occurred while publishing. Please try again.')
        ->and($this->postPlatform->error_message)->not->toContain('/home/forge');
});

test('publish to social platform marks account as token expired on auth failure', function () {
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '401'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->socialAccount->status)->toBe(AccountStatus::TokenExpired);
});

test('publish reschedules platform unavailable retry via Bus dispatch (not marked Failed, not expired)', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException(
            'Facebook API returned 503 during token refresh',
            503,
            ['operation_id' => 'operation-123'],
        )
    );

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Retrying);
    expect($this->postPlatform->error_context['category'] ?? null)->toBe('platform_unavailable');
    expect($this->postPlatform->error_context['http_status'] ?? null)->toBe(503);
    expect($this->postPlatform->error_context['retry_count'] ?? null)->toBe(1);
    expect($this->postPlatform->error_context['operation_id'] ?? null)->toBe('operation-123');
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.platform_unavailable'));
    expect($this->postPlatform->error_context['detail'] ?? null)->toContain('Facebook API returned 503');
    expect($this->socialAccount->status)->toBe(AccountStatus::Connected);

    Bus::assertDispatched(PublishToSocialPlatform::class, function ($job) {
        return $job->postPlatform->id === $this->postPlatform->id
            && $job->uniqueAttempt === 1
            && $job->uniqueId() === "{$this->postPlatform->id}:1";
    });
});

test('publish reschedules retry when retry-refresh path hits platform unavailable', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    // Publisher first throws TokenExpired (401-style), the retry-refresh
    // path goes through ConnectionVerifier::verify which can in turn raise
    // PlatformUnavailable if the platform is down.
    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '401'));

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->andThrow(
        new PlatformUnavailableException('Facebook API returned 503 during token refresh', 503)
    );

    $this->app->instance(FacebookPublisher::class, $publisher);
    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Retrying);
    expect($this->postPlatform->error_context['category'] ?? null)->toBe('platform_unavailable');
    expect($this->socialAccount->status)->toBe(AccountStatus::Connected);

    Bus::assertDispatched(PublishToSocialPlatform::class);
});

test('publish reschedules retry exactly 10 minutes into the future', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $now = now()->startOfMinute();
    Carbon::setTestNow($now);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    // error_context tracks the next attempt — must be exactly +10 min
    expect($this->postPlatform->error_context['next_attempt_at'] ?? null)
        ->toBe($now->copy()->addMinutes(10)->toIso8601String());

    // The actual dispatched job carries the same delay
    Bus::assertDispatched(PublishToSocialPlatform::class, function ($job) use ($now) {
        // $job->delay is a Carbon|DateInterval|int set by ->delay(...)
        $delayAt = $job->delay instanceof DateTimeInterface
            ? Carbon::instance($job->delay)
            : null;

        return $delayAt !== null
            && $delayAt->equalTo($now->copy()->addMinutes(10));
    });

    Carbon::setTestNow();
});

test('publish honors a platform-specific retry delay and retry limit', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $now = now()->startOfSecond();
    Carbon::setTestNow($now);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new PlatformUnavailableException(
        message: 'Remote operation is still processing',
        context: ['operation_id' => 'operation-123'],
        retryDelaySeconds: 30,
        maxRetries: 2,
    ));
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->error_context['next_attempt_at'] ?? null)
        ->toBe($now->copy()->addSeconds(30)->toIso8601String());

    Bus::assertDispatched(PublishToSocialPlatform::class, function ($job) use ($now) {
        return $job->delay instanceof DateTimeInterface
            && Carbon::instance($job->delay)->equalTo($now->copy()->addSeconds(30));
    });

    $this->postPlatform->update(['error_context' => ['retry_count' => 2]]);
    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Failed);

    Carbon::setTestNow();
});

test('publish records last_attempt_at when rescheduling for retry', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $now = now()->startOfMinute();
    Carbon::setTestNow($now);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->error_context['last_attempt_at'] ?? null)
        ->toBe($now->toIso8601String());

    Carbon::setTestNow();
});

test('publish preserves resumable context when a later transient error has no context', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $checkpoint = [
        'tiktok_publish_id' => 'publish-123',
        'tiktok_derivative_paths' => ['social-tiktok-photos/pending.jpg'],
        'retry_count' => 2,
    ];
    $this->postPlatform->update(['error_context' => $checkpoint]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Token refresh service unavailable', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    $context = $this->postPlatform->fresh()->error_context;

    expect($context['tiktok_publish_id'] ?? null)->toBe('publish-123')
        ->and($context['tiktok_derivative_paths'] ?? null)->toBe(['social-tiktok-photos/pending.jpg'])
        ->and($context['retry_count'] ?? null)->toBe(3)
        ->and($context['http_status'] ?? null)->toBe(503);
});

test('publish preserves a resumable retry policy after the global retry limit', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $this->postPlatform->update([
        'error_context' => [
            'tiktok_publish_id' => 'publish-123',
            'retry_count' => 7,
            'max_retries' => 90,
            'retry_delay_seconds' => 10,
        ],
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Token refresh service unavailable', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    $context = $this->postPlatform->fresh()->error_context;

    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Retrying)
        ->and($context['retry_count'] ?? null)->toBe(8)
        ->and($context['max_retries'] ?? null)->toBe(90)
        ->and($context['retry_delay_seconds'] ?? null)->toBe(10)
        ->and($context['tiktok_publish_id'] ?? null)->toBe('publish-123');
});

test('post stays in Publishing while one platform is still Retrying', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    // Second Facebook account on the same post — first one will publish OK,
    // second one will hit PlatformUnavailable and reschedule.
    $secondAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $secondPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $secondAccount->id,
        'enabled' => true,
        'status' => PlatformStatus::Published,  // simulate already published
        'platform_post_id' => 'sibling-123',
    ]);

    // Start the post as Publishing so updatePostStatus sees the in-flight context
    $this->post->update(['status' => PostStatus::Publishing]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->post->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Retrying);
    // Post is NOT finalized because one of its platforms is still pending retry.
    expect($this->post->status)->toBe(PostStatus::Publishing);
});

test('successful publish after a retry transitions the platform to Published', function () {
    // Pre-condition: this platform already failed once and is currently Retrying.
    $this->postPlatform->update([
        'status' => PlatformStatus::Retrying,
        'error_context' => ['retry_count' => 3, 'category' => 'platform_unavailable'],
    ]);

    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-after-retry',
        'url' => 'https://facebook.com/post/after-retry',
    ]);
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Published);
    expect($this->postPlatform->platform_post_id)->toBe('post-after-retry');
});

test('publish retry count increments across successive platform_unavailable attempts', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    // Simulate prior attempts
    $this->postPlatform->update([
        'error_context' => ['retry_count' => 5],
    ]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Retrying);
    expect($this->postPlatform->error_context['retry_count'] ?? null)->toBe(6);
});

test('publish hard-fails when platform unavailable retries are exhausted', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    $this->postPlatform->update([
        'error_context' => ['retry_count' => PublishToSocialPlatform::MAX_PLATFORM_UNAVAILABLE_RETRIES],
    ]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed)
        ->and($this->postPlatform->error_message)->toBe(__('posts.errors.platform_unavailable_exhausted'))
        ->and($this->postPlatform->error_context['category'] ?? null)->toBe('platform_unavailable')
        ->and($this->postPlatform->error_context['retry_count'] ?? null)->toBe(PublishToSocialPlatform::MAX_PLATFORM_UNAVAILABLE_RETRIES + 1)
        ->and($this->postPlatform->error_context['detail'] ?? null)->toContain('Facebook 503');

    Bus::assertNotDispatched(PublishToSocialPlatform::class);
});

test('publish keeps a resumable checkpoint when platform unavailable retries are exhausted', function () {
    Bus::fake([PublishToSocialPlatform::class]);
    Event::fake();
    Mail::fake();

    $this->postPlatform->update([
        'error_context' => [
            'tiktok_publish_id' => 'pub_in_flight',
            'retry_count' => PublishToSocialPlatform::MAX_PLATFORM_UNAVAILABLE_RETRIES,
        ],
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new PlatformUnavailableException('Facebook 503', 503)
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    $context = $this->postPlatform->fresh()->error_context;

    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Failed)
        ->and($context['tiktok_publish_id'] ?? null)->toBe('pub_in_flight')
        ->and($context['category'] ?? null)->toBe('platform_unavailable');

    Bus::assertNotDispatched(PublishToSocialPlatform::class);
});

test('publish skips platforms that are already failed', function () {
    Event::fake();
    Mail::fake();

    $this->postPlatform->update([
        'status' => PlatformStatus::Failed,
        'error_message' => __('posts.errors.publishing_timed_out'),
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldNotReceive('publish');
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed)
        ->and($this->postPlatform->error_message)->toBe(__('posts.errors.publishing_timed_out'));
});

test('publish job unique id includes the platform and attempt', function () {
    $job = new PublishToSocialPlatform($this->postPlatform, 3);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe("{$this->postPlatform->id}:3")
        ->and($job->uniqueFor)->toBe(960);
});

test('publish job prevents concurrent execution across different attempts', function () {
    $job = new PublishToSocialPlatform($this->postPlatform, 3);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe("social-publish:{$this->postPlatform->id}")
        ->and($middleware[0]->releaseAfter)->toBe(60)
        ->and($middleware[0]->expiresAfter)->toBe($job->timeout + 60)
        ->and($job->tries)->toBe(20)
        ->and($job->maxExceptions)->toBe(1);
});

test('publish job releases an overlapping execution for the same platform', function () {
    $runningJob = new PublishToSocialPlatform($this->postPlatform, 0);
    $overlappingJob = (new PublishToSocialPlatform($this->postPlatform, 1))->withFakeQueueInteractions();
    /** @var WithoutOverlapping $middleware */
    $middleware = $overlappingJob->middleware()[0];
    $lock = Cache::lock($middleware->getLockKey($runningJob), $runningJob->timeout + 60);
    $handled = false;

    expect($middleware->getLockKey($runningJob))->toBe($middleware->getLockKey($overlappingJob))
        ->and($lock->get())->toBeTrue();

    try {
        $middleware->handle($overlappingJob, function () use (&$handled): void {
            $handled = true;
        });
    } finally {
        $lock->release();
    }

    expect($handled)->toBeFalse();
    $overlappingJob->assertReleased(60);

    $middleware->handle($overlappingJob, function () use (&$handled): void {
        $handled = true;
    });

    expect($handled)->toBeTrue();
});

test('publish job unique lock drops a duplicate dispatch for the same platform attempt', function () {
    Bus::fake([PublishToSocialPlatform::class]);

    PublishToSocialPlatform::dispatch($this->postPlatform, 0);
    PublishToSocialPlatform::dispatch($this->postPlatform, 0);

    Bus::assertDispatchedTimes(PublishToSocialPlatform::class, 1);
});

test('publish job unique lock allows concurrent dispatches for different attempts', function () {
    Bus::fake([PublishToSocialPlatform::class]);

    PublishToSocialPlatform::dispatch($this->postPlatform, 0);
    PublishToSocialPlatform::dispatch($this->postPlatform, 1);

    Bus::assertDispatchedTimes(PublishToSocialPlatform::class, 2);
    Bus::assertDispatched(PublishToSocialPlatform::class, fn ($job) => $job->uniqueAttempt === 0);
    Bus::assertDispatched(PublishToSocialPlatform::class, fn ($job) => $job->uniqueAttempt === 1);
});

test('failed hook skips platforms that are already failed', function () {
    Event::fake();
    Mail::fake();

    $this->postPlatform->update([
        'status' => PlatformStatus::Failed,
        'error_message' => __('posts.errors.publishing_timed_out'),
        'error_context' => ['category' => 'timeout'],
    ]);

    (new PublishToSocialPlatform($this->postPlatform))->failed(new TypeError('Simulated worker kill'));

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed)
        ->and($this->postPlatform->error_message)->toBe(__('posts.errors.publishing_timed_out'))
        ->and($this->postPlatform->error_context['category'] ?? null)->toBe('timeout');
});

test('failed hook skips platforms that are already published', function () {
    Event::fake();
    Mail::fake();

    $this->postPlatform->update([
        'status' => PlatformStatus::Published,
        'platform_post_id' => 'already-published',
        'error_message' => null,
    ]);

    (new PublishToSocialPlatform($this->postPlatform))->failed(new TypeError('Simulated worker kill'));

    $this->postPlatform->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Published)
        ->and($this->postPlatform->platform_post_id)->toBe('already-published')
        ->and($this->postPlatform->error_message)->toBeNull();
});

test('failed hook keeps TikTok photo derivatives while a publish_id can be resumed', function () {
    Event::fake();
    Mail::fake();
    Storage::fake();

    $path = 'social-tiktok-photos/123e4567-e89b-12d3-a456-426614174000.jpg';
    $unrelatedPath = 'customer-media/keep.jpg';
    Storage::put($path, 'image');
    Storage::put($unrelatedPath, 'image');
    $this->postPlatform->update([
        'platform' => Platform::TikTok,
        'status' => PlatformStatus::Retrying,
        'error_context' => [
            'tiktok_publish_id' => 'publish-123',
            'tiktok_derivative_paths' => [$path, 'social-tiktok-photos/../customer-media/keep.jpg'],
        ],
    ]);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->failed(new TypeError('Simulated worker kill'));

    Storage::assertExists($path);
    Storage::assertExists($unrelatedPath);
    expect($this->postPlatform->fresh()->error_context)->toMatchArray([
        'tiktok_publish_id' => 'publish-123',
        'category' => 'job_failed',
    ]);
});

test('failed hook prunes TikTok photo derivatives when there is no publish_id', function () {
    Event::fake();
    Mail::fake();
    Storage::fake();

    $path = 'social-tiktok-photos/123e4567-e89b-12d3-a456-426614174000.jpg';
    Storage::put($path, 'image');
    $this->postPlatform->update([
        'platform' => Platform::TikTok,
        'status' => PlatformStatus::Retrying,
        'error_context' => [
            'tiktok_derivative_paths' => [$path],
        ],
    ]);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->failed(new TypeError('Simulated worker kill'));

    Storage::assertMissing($path);
    expect($this->postPlatform->fresh()->error_context['category'] ?? null)->toBe('job_failed');
});

test('terminal TikTok account guards keep derivatives while a publish_id can be resumed', function (string $guard) {
    Event::fake();
    Mail::fake();
    Storage::fake();

    $path = 'social-tiktok-photos/'.fake()->uuid().'.jpg';
    Storage::put($path, 'image');

    $accountAttributes = match ($guard) {
        'inactive' => ['is_active' => false],
        'disconnected' => ['status' => AccountStatus::Disconnected],
        'token_expired' => ['status' => AccountStatus::TokenExpired],
        'missing_scopes' => ['scopes' => []],
    };
    $account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        ...$accountAttributes,
    ]);
    $platform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'status' => PlatformStatus::Retrying,
        'error_context' => [
            'tiktok_publish_id' => "publish-{$guard}",
            'tiktok_derivative_paths' => [$path],
        ],
    ]);

    (new PublishToSocialPlatform($platform))->handle();

    Storage::assertExists($path);
    $platform->refresh();

    expect($platform->status)->toBe(PlatformStatus::Failed)
        ->and($platform->error_context['tiktok_publish_id'] ?? null)->toBe("publish-{$guard}");

    if ($guard === 'missing_scopes') {
        expect($platform->error_message)->toBe('Missing permissions: video.publish. Please reconnect your account.')
            ->and($platform->error_context['category'] ?? null)->toBe('permission')
            ->and($platform->error_context['missing_scopes'] ?? null)->toBe(['video.publish']);

        return;
    }

    $translationKey = match ($guard) {
        'inactive' => 'posts.errors.account_inactive',
        'disconnected' => 'posts.errors.account_disconnected',
        'token_expired' => 'posts.errors.account_token_expired',
    };

    expect($platform->error_message)->toBe(__($translationKey));
})->with([
    'inactive account' => 'inactive',
    'disconnected account' => 'disconnected',
    'expired token' => 'token_expired',
    'missing publish scopes' => 'missing_scopes',
]);

test('terminal TikTok account guards prune derivatives when there is no publish_id', function (string $guard) {
    Event::fake();
    Mail::fake();
    Storage::fake();

    $path = 'social-tiktok-photos/'.fake()->uuid().'.jpg';
    Storage::put($path, 'image');

    $accountAttributes = match ($guard) {
        'inactive' => ['is_active' => false],
        'disconnected' => ['status' => AccountStatus::Disconnected],
        'token_expired' => ['status' => AccountStatus::TokenExpired],
        'missing_scopes' => ['scopes' => []],
    };
    $account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        ...$accountAttributes,
    ]);
    $platform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'status' => PlatformStatus::Retrying,
        'error_context' => [
            'tiktok_derivative_paths' => [$path],
        ],
    ]);

    (new PublishToSocialPlatform($platform))->handle();

    Storage::assertMissing($path);
    expect($platform->fresh()->status)->toBe(PlatformStatus::Failed)
        ->and($platform->fresh()->error_context['tiktok_publish_id'] ?? null)->toBeNull();
})->with([
    'inactive account' => 'inactive',
    'disconnected account' => 'disconnected',
    'expired token' => 'token_expired',
    'missing publish scopes' => 'missing_scopes',
]);

test('tiktok photo publish resumes after a status-fetch token expiry without a second init', function () {
    Event::fake();
    Mail::fake();
    Storage::fake();

    $account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'username' => 'tiktoker',
        'token_expires_at' => now()->addDay(),
    ]);
    $this->post->update([
        'media' => [[
            'id' => 'oversized',
            'path' => 'media/2026-01/big.jpg',
            'url' => 'https://example.com/media/2026-01/big.jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => 'big.jpg',
            'meta' => ['width' => 1254, 'height' => 1254],
        ]],
    ]);
    $platform = PostPlatform::factory()->tiktok()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'status' => PlatformStatus::Pending,
        'enabled' => true,
        'meta' => ['privacy_level' => PrivacyLevel::SelfOnly->value],
    ]);

    $mockOptimizer = Mockery::mock(MediaOptimizer::class);
    $mockOptimizer->shouldReceive('maxWidthForPlatform')->with(Platform::TikTok)->andReturn(1080);
    $mockOptimizer->shouldReceive('optimizeImage')->with(Mockery::type('string'), Platform::TikTok)->andReturnUsing(function (string $tempFile) {
        $optimized = tempnam(sys_get_temp_dir(), 'tt_opt_');
        copy($tempFile, $optimized);

        return $optimized;
    });
    app()->instance(MediaOptimizer::class, $mockOptimizer);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(true);
    $this->app->instance(ConnectionVerifier::class, $verifier);

    $api = config('trypost.platforms.tiktok.api');

    Http::fake([
        $api.'/post/publish/content/init/' => Http::response(['data' => ['publish_id' => 'pub_job_401']]),
        $api.'/post/publish/status/fetch/' => Http::sequence()
            ->push([
                'error' => [
                    'code' => 'access_token_invalid',
                    'message' => 'Access token is invalid',
                ],
            ], 401)
            ->push([
                'data' => [
                    'status' => 'PUBLISH_COMPLETE',
                    'publicaly_available_post_id' => ['video_123'],
                ],
            ]),
        '*' => Http::response('fake-image-content', 200),
    ]);

    (new PublishToSocialPlatform($platform))->handle();

    $platform->refresh();

    expect($platform->status)->toBe(PlatformStatus::Published)
        ->and($platform->platform_post_id)->toBe('video_123')
        ->and($platform->error_context)->toBeNull()
        ->and(Storage::allFiles('social-tiktok-photos'))->toBeEmpty()
        ->and(Http::recorded(fn ($request) => str_contains($request->url(), '/post/publish/content/init/')))
        ->toHaveCount(1);
});

test('publish to social platform updates post status when all platforms finished', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Published);
});

test('publish to social platform marks post as partially published when some fail', function () {
    Event::fake();

    $socialAccount2 = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $postPlatform2 = PostPlatform::factory()->tiktok()->failed()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $socialAccount2->id,
        'enabled' => true,
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::PartiallyPublished);
});

test('publish to social platform marks post as failed when all platforms fail', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API Error'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Failed);
});

test('publish to social platform skips publishing when account is disconnected', function () {
    Event::fake();

    $this->socialAccount->update([
        'status' => AccountStatus::Disconnected,
        'disconnected_at' => now(),
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_disconnected'));
});

test('publish to social platform skips publishing when account token is expired', function () {
    Event::fake();

    $this->socialAccount->update([
        'status' => AccountStatus::TokenExpired,
        'disconnected_at' => now(),
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_token_expired'));
    expect($this->postPlatform->error_context['category'])->toBe('token_expired');
});

test('publish to social platform skips publishing when account is inactive', function () {
    Event::fake();

    $this->socialAccount->update(['is_active' => false]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toBe(__('posts.errors.account_inactive'));
});

test('publish to social platform dispatches success notification when all platforms published', function () {
    Event::fake();
    Queue::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'post-123',
        'url' => 'https://facebook.com/post/123',
    ]);

    $this->app->instance(FacebookPublisher::class, $publisher);

    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Published);
    Queue::assertPushed(SendNotification::class);
});

test('publish to social platform dispatches failure notification when platform fails', function () {
    Event::fake();
    Queue::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API error'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Failed);
    Queue::assertPushed(SendNotification::class);
});

test('in-app published notification falls back to the facebook page display name', function () {
    Event::fake();
    Queue::fake();

    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'username' => null,
        'display_name' => 'InboxPlacement.io',
    ]);
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $postPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => $account->platform,
        'enabled' => true,
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andReturn([
        'id' => 'fb-123',
        'url' => 'https://www.facebook.com/permalink.php?story_fbid=pfbid0&id=61592851040951',
    ]);
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($postPlatform))->handle();

    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($post) {
        $platforms = 'Facebook Page (@InboxPlacement.io)';

        return $job->type === Type::PostPublished
            && $job->title === __('notifications.post_published.title')
            && $job->body === __('notifications.post_published.body', ['platforms' => $platforms])
            && data_get($job->data, 'post_id') === $post->id;
    });
});

test('in-app failed notification falls back to the facebook page display name', function () {
    Event::fake();
    Queue::fake();

    $account = SocialAccount::factory()->facebook()->create([
        'workspace_id' => $this->workspace->id,
        'username' => null,
        'display_name' => 'InboxPlacement.io',
    ]);
    $post = Post::factory()->scheduled()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $postPlatform = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'platform' => $account->platform,
        'enabled' => true,
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('API error'));
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($postPlatform))->handle();

    Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($post) {
        $platforms = 'Facebook Page (@InboxPlacement.io)';

        return $job->type === Type::PostFailed
            && $job->title === __('notifications.post_failed.title')
            && $job->body === __('notifications.post_failed.body', ['platforms' => $platforms])
            && data_get($job->data, 'post_id') === $post->id;
    });
});

test('it retries with token refresh when token expires during publish', function () {
    Event::fake();

    $callCount = 0;
    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new TokenExpiredException('Token expired', '401');
            }

            return ['id' => 'post-123', 'url' => 'https://facebook.com/post/123'];
        });

    $this->app->instance(FacebookPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn(true);

    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Published);
    expect($this->socialAccount->status)->not->toBe(AccountStatus::Disconnected);
});

test('it marks account as token expired when refresh fails during publish retry', function () {
    Event::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->andThrow(new TokenExpiredException('Token expired', '401'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->once()->andThrow(new Exception('Refresh failed'));

    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    $this->socialAccount->refresh();

    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->socialAccount->status)->toBe(AccountStatus::TokenExpired);
});

test('publish to social platform skips if already published (idempotency)', function () {
    Event::fake();

    // Mark as already published
    $this->postPlatform->update([
        'status' => PlatformStatus::Published,
        'platform_post_id' => 'existing-123',
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldNotReceive('publish');

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    // Should not have called publish
    $this->postPlatform->refresh();
    expect($this->postPlatform->platform_post_id)->toBe('existing-123');
});

test('publish to social platform saves error context on generic failure', function () {
    Event::fake();

    $this->post->update(['content' => 'Test content here']);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new Exception('Something broke'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('unknown');
    expect($this->postPlatform->error_context['failed_at'])->toBeString();
    expect($this->postPlatform->error_context['content_length'])->toBe(17);
    expect($this->postPlatform->error_context['media_count'])->toBe(0);
});

test('publish to social platform saves error context on social publish exception', function () {
    Event::fake();

    $this->post->update(['content' => 'Hello world']);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new FacebookPublishException(
            'Not authorized to post',
            ErrorCategory::Permission,
            '403',
            '{"error": "forbidden"}',
        )
    );

    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('permission');
    expect($this->postPlatform->error_context['platform_error_code'])->toBe('403');
    expect($this->postPlatform->error_context['content_length'])->toBe(11);
    expect($this->postPlatform->error_context['raw_response'])->toBe('{"error": "forbidden"}');
});

test('publish keeps a resumable checkpoint when a later publish exception is terminal', function () {
    Event::fake();

    $this->postPlatform->update([
        'error_context' => [
            'tiktok_publish_id' => 'pub_dead',
        ],
    ]);

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(
        new FacebookPublishException(
            'Video rejected',
            ErrorCategory::ContentPolicy,
            'video_rejected',
            '{"status":"FAILED"}',
        )
    );
    $this->app->instance(FacebookPublisher::class, $publisher);

    (new PublishToSocialPlatform($this->postPlatform->fresh()))->handle();

    $context = $this->postPlatform->fresh()->error_context;

    expect($this->postPlatform->fresh()->status)->toBe(PlatformStatus::Failed)
        ->and($context['tiktok_publish_id'] ?? null)->toBe('pub_dead')
        ->and($context['category'] ?? null)->toBe('content_policy');
});

test('publish to social platform fails when scopes are missing', function () {
    Event::fake();

    $this->socialAccount->update(['scopes' => ['user.info.basic']]); // missing pages_manage_posts
    $this->postPlatform->refresh();

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->status)->toBe(PlatformStatus::Failed);
    expect($this->postPlatform->error_message)->toContain('Missing permissions');
    expect($this->postPlatform->error_context['category'])->toBe('permission');
    expect($this->postPlatform->error_context['missing_scopes'])->toContain('pages_manage_posts');
});

test('publish to social platform saves error context on token expired', function () {
    Event::fake();
    Mail::fake();

    $publisher = Mockery::mock(FacebookPublisher::class);
    $publisher->shouldReceive('publish')->andThrow(new TokenExpiredException('Token expired', '190'));

    $this->app->instance(FacebookPublisher::class, $publisher);

    $verifier = Mockery::mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->andThrow(new TokenExpiredException('Refresh failed'));
    $this->app->instance(ConnectionVerifier::class, $verifier);

    (new PublishToSocialPlatform($this->postPlatform))->handle();

    $this->postPlatform->refresh();
    expect($this->postPlatform->error_context)->toBeArray();
    expect($this->postPlatform->error_context['category'])->toBe('token_expired');
    expect($this->postPlatform->error_context['platform_error_code'])->toBe('190');
});
