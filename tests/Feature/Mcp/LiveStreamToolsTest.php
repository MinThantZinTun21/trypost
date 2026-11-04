<?php

declare(strict_types=1);

use App\Dto\StreamReadiness;
use App\Enums\LiveStream\Privacy;
use App\Enums\LiveStream\Status;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\YouTubeLiveException;
use App\Jobs\RunLiveStream;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreateLiveStream;
use App\Mcp\Tools\GetLiveStream;
use App\Mcp\Tools\ListLiveStreams;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Media\StreamProbe;
use App\Services\Social\YouTubeLive;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->channel = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $this->video = Video::factory()->for($this->workspace)->withFile()->create(['name' => 'Dhamma talk']);

    $this->probe = Mockery::mock(StreamProbe::class);
    $this->probe->shouldReceive('probe')->andReturn(new StreamReadiness('h264', 'aac', 2.0, 6_000_000))->byDefault();
    app()->instance(StreamProbe::class, $this->probe);

    $this->youtube = Mockery::mock(YouTubeLive::class);
    $this->youtube->shouldReceive('createBroadcast')->andReturn([
        'broadcast_id' => 'bc123',
        'stream_id' => 'st456',
        'ingest_url' => 'rtmp://a.rtmp.youtube.com/live2/key-789',
    ])->byDefault();
    app()->instance(YouTubeLive::class, $this->youtube);
});

function liveStreamInput(array $overrides = []): array
{
    return [
        'video_id' => test()->video->id,
        'social_account_id' => test()->channel->id,
        'title' => 'Evening Dhamma talk',
        'description' => 'Recorded talk, played live.',
        'mode' => 'scheduled',
        'starts_at' => now()->addDay()->startOfHour()->toIso8601String(),
        ...$overrides,
    ];
}

test('create_live_stream schedules a broadcast on the youtube channel', function () {
    $startsAt = now()->addDay()->startOfHour();
    $this->youtube->shouldReceive('createBroadcast')->once()->withArgs(fn (SocialAccount $account, array $details) => $account->is($this->channel)
        && $details['title'] === 'Evening Dhamma talk'
        && $details['description'] === 'Recorded talk, played live.'
        && $details['privacy'] === Privacy::Public
        && $details['starts_at']->equalTo($startsAt))->andReturn([
            'broadcast_id' => 'bc123',
            'stream_id' => 'st456',
            'ingest_url' => 'rtmp://a.rtmp.youtube.com/live2/key-789',
        ]);

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput(['starts_at' => $startsAt->toIso8601String()]))
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('live_stream.status', 'scheduled')
            ->where('live_stream.title', 'Evening Dhamma talk')
            ->where('live_stream.privacy', 'public')
            ->where('live_stream.video', ['id' => $this->video->id, 'name' => 'Dhamma talk'])
            ->where('live_stream.social_account_id', $this->channel->id)
            ->where('live_stream.starts_at', $startsAt->toIso8601String())
            ->where('live_stream.stops_at', null)
            ->where('live_stream.watch_url', 'https://www.youtube.com/watch?v=bc123')
            ->where('warnings', [])
            ->etc());

    $stream = LiveStream::query()->sole();
    expect($stream->status)->toBe(Status::Scheduled)
        ->and($stream->workspace_id)->toBe($this->workspace->id)
        ->and($stream->youtube_broadcast_id)->toBe('bc123')
        ->and($stream->youtube_stream_id)->toBe('st456')
        ->and($stream->ingest_url)->toBe('rtmp://a.rtmp.youtube.com/live2/key-789');
});

test('create_live_stream starting now starts the broadcast right away', function () {
    $this->freezeTime();
    $this->youtube->shouldReceive('createBroadcast')->once()->withArgs(fn (SocialAccount $account, array $details) => $details['starts_at']->equalTo(now())
        && $details['privacy'] === Privacy::Unlisted)->andReturn(['broadcast_id' => 'bc1', 'stream_id' => 'st1', 'ingest_url' => 'rtmp://x/y']);

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput(['mode' => 'now', 'starts_at' => null, 'privacy' => 'unlisted', 'stops_at' => now()->addHours(3)->toIso8601String()]))
        ->assertOk();

    expect(LiveStream::query()->sole()->stops_at->equalTo(now()->addHours(3)->startOfSecond()))->toBeTrue();
});

test('create_live_stream hands a stream starting now to the live worker, and leaves a scheduled one for its time', function () {
    Queue::fake();

    SchedulerServer::actingAs($this->owner)->tool(CreateLiveStream::class, liveStreamInput())->assertOk();
    Queue::assertNothingPushed();

    SchedulerServer::actingAs($this->owner)->tool(CreateLiveStream::class, liveStreamInput(['mode' => 'now', 'starts_at' => null]))->assertOk();
    Queue::assertPushedOn('live', RunLiveStream::class, fn (RunLiveStream $job) => $job->liveStream->is(LiveStream::query()->latest()->latest('id')->first()));
    Queue::assertPushed(RunLiveStream::class, 1);
});

test('create_live_stream sets the thumbnail from an uploaded image', function () {
    $image = Media::factory()->for($this->workspace, 'mediable')->create(['collection' => 'assets', 'mime_type' => 'image/png', 'path' => 'medias/thumb.png']);
    $this->youtube->shouldReceive('setThumbnail')->once()->withArgs(fn (SocialAccount $account, string $broadcastId, Media $media) => $broadcastId === 'bc123' && $media->is($image));

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput(['thumbnail_media_id' => $image->id]))
        ->assertOk();
});

test('create_live_stream warns when the video bitrate is low', function () {
    $this->probe->shouldReceive('probe')->andReturn(new StreamReadiness('h264', 'aac', 2.0, 2_000_000));

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput())
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('warnings', [__('live_streams.low_bitrate', ['mbps' => '2.0'])])
            ->etc());
});

test('create_live_stream refuses a video that is not stream-ready', function (StreamReadiness $readiness, string $message) {
    $this->probe->shouldReceive('probe')->andReturn($readiness);
    $this->youtube->shouldNotReceive('createBroadcast');

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput())
        ->assertHasErrors([$message]);

    expect(LiveStream::query()->exists())->toBeFalse();
})->with([
    'not h264' => fn () => [new StreamReadiness('hevc', 'aac', 2.0, 6_000_000), __('live_streams.not_h264', ['codec' => 'hevc'])],
    'not aac' => fn () => [new StreamReadiness('h264', 'opus', 2.0, 6_000_000), __('live_streams.not_aac', ['codec' => 'opus'])],
    'no audio' => fn () => [new StreamReadiness('h264', null, 2.0, 6_000_000), __('live_streams.not_aac', ['codec' => 'none'])],
    'sparse keyframes' => fn () => [new StreamReadiness('h264', 'aac', 8.0, 6_000_000), __('live_streams.keyframes_too_far', ['seconds' => '8.0'])],
]);

test('create_live_stream refuses with youtube\'s reason when the channel cannot go live', function () {
    $this->youtube->shouldReceive('createBroadcast')->andThrow(new YouTubeLiveException('The channel is not enabled for live streaming.'));

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput())
        ->assertHasErrors([__('live_streams.youtube_refused', ['reason' => 'The channel is not enabled for live streaming.'])]);

    expect(LiveStream::query()->exists())->toBeFalse();
});

test('create_live_stream starting now is refused while another live stream is live', function () {
    LiveStream::factory()->for($this->workspace)->live()->create();
    $this->youtube->shouldNotReceive('createBroadcast');

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput(['mode' => 'now', 'starts_at' => null]))
        ->assertHasErrors([__('live_streams.one_at_a_time')]);
});

test('create_live_stream refuses an account that is not youtube, another workspace\'s video, and a bad input', function (array $input) {
    $this->youtube->shouldNotReceive('createBroadcast');

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateLiveStream::class, liveStreamInput($input))
        ->assertHasErrors();
})->with([
    'facebook page' => fn () => ['social_account_id' => SocialAccount::factory()->create(['workspace_id' => test()->workspace->id, 'platform' => Platform::Facebook])->id],
    'other workspace video' => fn () => ['video_id' => Video::factory()->withFile()->create()->id],
    'title too long' => fn () => ['title' => str_repeat('a', 101)],
    'past start' => fn () => ['starts_at' => now()->subHour()->toIso8601String()],
    'stop before start' => fn () => ['stops_at' => now()->addHour()->toIso8601String()],
    'bad privacy' => fn () => ['privacy' => 'secret'],
]);

test('list_live_streams lists the owner\'s live streams newest first', function () {
    $older = LiveStream::factory()->for($this->workspace)->create(['created_at' => now()->subDay()]);
    $newer = LiveStream::factory()->for($this->workspace)->live()->create();
    LiveStream::factory()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool(ListLiveStreams::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('live_streams', 2)
            ->where('live_streams.0.id', $newer->id)
            ->where('live_streams.0.status', 'live')
            ->where('live_streams.1.id', $older->id)
            ->etc());
});

test('get_live_stream returns one live stream and refuses another workspace\'s', function () {
    $stream = LiveStream::factory()->for($this->workspace)->create(['title' => 'Morning talk']);

    SchedulerServer::actingAs($this->owner)
        ->tool(GetLiveStream::class, ['id' => $stream->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('live_stream.id', $stream->id)
            ->where('live_stream.title', 'Morning talk')
            ->etc());

    SchedulerServer::actingAs($this->owner)
        ->tool(GetLiveStream::class, ['id' => LiveStream::factory()->create()->id])
        ->assertHasErrors([__('mcp.live_stream.not_found')]);
});
