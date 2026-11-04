<?php

declare(strict_types=1);

use App\Actions\LiveStream\StopLiveStream as StopLiveStreamAction;
use App\Console\Commands\StopDueLiveStreams;
use App\Enums\LiveStream\Status;
use App\Exceptions\Social\YouTubeLiveException;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\StopLiveStream;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\YouTubeLive;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->channel = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id]);

    $this->youtube = Mockery::mock(YouTubeLive::class);
    app()->instance(YouTubeLive::class, $this->youtube);
});

test('stop_live_stream ends a live broadcast and keeps its recording', function () {
    $this->freezeTime();
    $stream = LiveStream::factory()->for($this->workspace)->live()->create(['social_account_id' => $this->channel->id, 'youtube_broadcast_id' => 'bc123']);
    $this->youtube->shouldReceive('complete')->once()->withArgs(fn (SocialAccount $account, string $id) => $account->is($this->channel) && $id === 'bc123');

    SchedulerServer::actingAs($this->owner)
        ->tool(StopLiveStream::class, ['id' => $stream->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('live_stream.status', 'stopped')
            ->where('live_stream.ended_at', now()->toIso8601String())
            ->etc());

    expect($stream->fresh()->status)->toBe(Status::Stopped);
});

test('stop_live_stream on a scheduled one removes its broadcast from youtube', function () {
    $stream = LiveStream::factory()->for($this->workspace)->create(['social_account_id' => $this->channel->id, 'youtube_broadcast_id' => 'bc999']);
    $this->youtube->shouldReceive('delete')->once()->withArgs(fn (SocialAccount $account, string $id) => $id === 'bc999');

    SchedulerServer::actingAs($this->owner)
        ->tool(StopLiveStream::class, ['id' => $stream->id])
        ->assertOk();

    expect($stream->fresh()->status)->toBe(Status::Stopped);
});

test('stop_live_stream still stops it when youtube refuses to end the broadcast', function () {
    $stream = LiveStream::factory()->for($this->workspace)->live()->create(['social_account_id' => $this->channel->id]);
    $this->youtube->shouldReceive('complete')->andThrow(new YouTubeLiveException('Invalid transition.'));

    SchedulerServer::actingAs($this->owner)
        ->tool(StopLiveStream::class, ['id' => $stream->id])
        ->assertOk();

    expect($stream->fresh()->status)->toBe(Status::Stopped);
});

test('stop_live_stream refuses a stopped or failed live stream, and another workspace\'s', function () {
    $this->youtube->shouldNotReceive('complete', 'delete');
    $stopped = LiveStream::factory()->for($this->workspace)->create(['status' => Status::Stopped]);
    $failed = LiveStream::factory()->for($this->workspace)->create(['status' => Status::Failed]);

    SchedulerServer::actingAs($this->owner)->tool(StopLiveStream::class, ['id' => $stopped->id])
        ->assertHasErrors([__('live_streams.not_active')]);
    SchedulerServer::actingAs($this->owner)->tool(StopLiveStream::class, ['id' => $failed->id])
        ->assertHasErrors([__('live_streams.not_active')]);
    SchedulerServer::actingAs($this->owner)->tool(StopLiveStream::class, ['id' => LiveStream::factory()->live()->create()->id])
        ->assertHasErrors([__('mcp.live_stream.not_found')]);
});

test('a live stream is stopped when its stop time arrives', function () {
    $due = LiveStream::factory()->for($this->workspace)->live()->create(['social_account_id' => $this->channel->id, 'stops_at' => now()->subMinute()]);
    $later = LiveStream::factory()->for($this->workspace)->create(['social_account_id' => $this->channel->id, 'stops_at' => now()->addHour()]);
    $this->youtube->shouldReceive('complete')->once();

    $this->artisan(StopDueLiveStreams::class)->assertSuccessful();

    expect($due->fresh()->status)->toBe(Status::Stopped)
        ->and($later->fresh()->status)->toBe(Status::Scheduled);
});

test('stop_live_stream completes a broadcast that went live after it was loaded', function () {
    $stream = LiveStream::factory()->for($this->workspace)->create(['social_account_id' => $this->channel->id, 'youtube_broadcast_id' => 'bc1']);
    $loaded = $stream->fresh();
    $stream->update(['status' => Status::Live]);
    $this->youtube->shouldReceive('complete')->once();
    $this->youtube->shouldNotReceive('delete');

    StopLiveStreamAction::execute($loaded);

    expect($stream->fresh()->status)->toBe(Status::Stopped);
});
