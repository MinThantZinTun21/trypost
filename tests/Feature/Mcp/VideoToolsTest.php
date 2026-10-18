<?php

declare(strict_types=1);

use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\DeleteVideo;
use App\Mcp\Tools\GetVideo;
use App\Mcp\Tools\ListVideos;
use App\Models\Media;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('list_videos lists the owner\'s videos newest first', function () {
    $older = Video::factory()->for($this->workspace)->withFile(size: 2048, duration: 30.0)->create(['name' => 'Older', 'created_at' => now()->subDay()]);
    $newer = Video::factory()->for($this->workspace)->withFile()->create(['name' => 'Newer']);
    Video::factory()->withFile()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool(ListVideos::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('videos', 2)
            ->where('videos.0.id', $newer->id)
            ->where('videos.1', [
                'id' => $older->id,
                'name' => 'Older',
                'size' => 2048,
                'duration' => 30,
                'created_at' => $older->created_at->toIso8601String(),
            ])
            ->where('page', 1)
            ->where('last_page', 1)
            ->where('total', 2));
});

test('get_video returns the video with a download link that works', function () {
    $video = Video::factory()->for($this->workspace)->withFile()->create(['name' => 'Beach day']);
    Storage::put($video->file->path, 'video bytes');
    $downloadUrl = null;

    SchedulerServer::actingAs($this->owner)
        ->tool(GetVideo::class, ['id' => $video->id])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($video, &$downloadUrl) {
            $json->where('video.id', $video->id)
                ->where('video.name', 'Beach day')
                ->whereType('video.download_url', 'string')
                ->etc();
            $downloadUrl = $json->toArray()['video']['download_url'];
        });

    $this->get($downloadUrl)->assertOk()->assertDownload();
});

test('get_video and delete_video refuse another workspace\'s video', function (string $tool) {
    $video = Video::factory()->withFile()->create();

    SchedulerServer::actingAs($this->owner)
        ->tool($tool, ['id' => $video->id])
        ->assertHasErrors([__('mcp.video.not_found')]);

    expect($video->fresh())->not->toBeNull();
})->with([
    'get_video' => GetVideo::class,
    'delete_video' => DeleteVideo::class,
]);

test('delete_video removes the video and its file', function () {
    $video = Video::factory()->for($this->workspace)->withFile()->create();
    $path = $video->file->path;
    Storage::put($path, 'video bytes');

    SchedulerServer::actingAs($this->owner)
        ->tool(DeleteVideo::class, ['id' => $video->id])
        ->assertOk()
        ->assertStructuredContent(['deleted' => true, 'id' => $video->id]);

    expect(Video::query()->exists())->toBeFalse()
        ->and(Media::query()->exists())->toBeFalse();
    Storage::assertMissing($path);
});
