<?php

declare(strict_types=1);

use App\Enums\LiveStream\Status;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Social\YouTubeLive;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = mobileHeaders($this->owner);

    $this->youtube = Mockery::mock(YouTubeLive::class);
    app()->instance(YouTubeLive::class, $this->youtube);
});

test('it lists the owner\'s live streams newest first', function () {
    $video = Video::factory()->for($this->workspace)->create(['name' => 'Dhamma talk']);
    $older = LiveStream::factory()->for($this->workspace)->create(['created_at' => now()->subDay()]);
    $newer = LiveStream::factory()->for($this->workspace)->live()->create(['video_id' => $video->id, 'title' => 'Evening talk', 'youtube_broadcast_id' => 'bc123']);
    LiveStream::factory()->create();

    $this->getJson(route('mobile.v1.live-streams.index'), $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.status', 'live')
        ->assertJsonPath('data.0.title', 'Evening talk')
        ->assertJsonPath('data.0.video_name', 'Dhamma talk')
        ->assertJsonPath('data.0.watch_url', 'https://www.youtube.com/watch?v=bc123')
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonMissingPath('data.0.ingest_url');
});

test('it stops a live stream', function () {
    $channel = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);
    $stream = LiveStream::factory()->for($this->workspace)->live()->create(['social_account_id' => $channel->id]);
    $this->youtube->shouldReceive('complete')->once();

    $this->postJson(route('mobile.v1.live-streams.stop', $stream), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'stopped');

    expect($stream->fresh()->status)->toBe(Status::Stopped);
});

test('it refuses to stop one that already stopped, and another workspace\'s', function () {
    $stopped = LiveStream::factory()->for($this->workspace)->create(['status' => Status::Stopped]);

    $this->postJson(route('mobile.v1.live-streams.stop', $stopped), [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id' => __('live_streams.not_active')]);

    $this->postJson(route('mobile.v1.live-streams.stop', LiveStream::factory()->live()->create()), [], $this->headers)
        ->assertNotFound();
});

test('it needs a mobile token', function () {
    $this->getJson(route('mobile.v1.live-streams.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.live-streams.stop', LiveStream::factory()->create()))->assertUnauthorized();
});
