<?php

declare(strict_types=1);

use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreateVideo;
use App\Mcp\Tools\StartVideoUpload;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Cache::flush();
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->cloud = Mockery::mock(ChunkedCloudUploader::class);
    $this->cloud->shouldReceive('isObjectStorageDisk')->andReturn(true)->byDefault();
    $this->cloud->shouldReceive('presignUpload')->andReturn([
        'key' => 'medias/talk.mp4',
        'url' => 'https://bucket.example.test/medias/talk.mp4?X-Amz-Signature=abc',
        'headers' => ['Content-Type' => 'video/mp4'],
    ])->byDefault();
    app()->instance(ChunkedCloudUploader::class, $this->cloud);
});

test('start_video_upload returns a presigned put url with a curl command', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(StartVideoUpload::class, ['file_name' => 'Talk.MP4', 'size' => 4096])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->whereType('upload_id', 'string')
            ->where('method', 'PUT')
            ->where('url', 'https://bucket.example.test/medias/talk.mp4?X-Amz-Signature=abc')
            ->where('headers', ['Content-Type' => 'video/mp4'])
            ->where('expires_in_seconds', ChunkedCloudUploader::UPLOAD_URL_TTL_SECONDS)
            ->where('curl', "curl --fail -X PUT -T '/path/to/talk.mp4' -H 'Content-Type: video/mp4' 'https://bucket.example.test/medias/talk.mp4?X-Amz-Signature=abc'"));
});

test('start_video_upload refuses a file that is not a video or is too large', function (string $fileName, int $size) {
    $this->cloud->shouldNotReceive('presignUpload');

    SchedulerServer::actingAs($this->owner)
        ->tool(StartVideoUpload::class, ['file_name' => $fileName, 'size' => $size])
        ->assertHasErrors();
})->with([
    'a photo' => ['photo.jpg', 4096],
    'too large' => ['talk.mp4', PHP_INT_MAX],
    'empty' => ['talk.mp4', 0],
]);

test('start_video_upload refuses without object storage', function () {
    $this->cloud->shouldReceive('isObjectStorageDisk')->andReturn(false);

    SchedulerServer::actingAs($this->owner)
        ->tool(StartVideoUpload::class, ['file_name' => 'talk.mp4', 'size' => 4096])
        ->assertHasErrors([__('mcp.upload.not_object_storage')]);
});

function startMcpVideoUpload(User $owner): string
{
    $uploadId = '';

    SchedulerServer::actingAs($owner)
        ->tool(StartVideoUpload::class, ['file_name' => 'talk.mp4', 'size' => 4096])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$uploadId) {
            $uploadId = $json->toArray()['upload_id'];
            $json->etc();
        });

    return $uploadId;
}

test('create_video registers a finished upload as a named video', function () {
    $this->cloud->shouldReceive('storedSize')->with('medias/talk.mp4')->andReturn(4096);
    $this->cloud->shouldReceive('storedMimeType')->andReturn('video/mp4');
    $this->cloud->shouldReceive('readRange')->andThrow(new RuntimeException('range GET failed'));
    $uploadId = startMcpVideoUpload($this->owner);

    SchedulerServer::actingAs($this->owner)
        ->tool(CreateVideo::class, ['upload_id' => $uploadId, 'name' => 'Dhamma talk'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('video.id', Video::query()->sole()->id)
            ->where('video.name', 'Dhamma talk')
            ->where('video.size', 4096)
            ->etc());

    $video = Video::query()->sole();
    expect($video->workspace_id)->toBe($this->workspace->id)
        ->and($video->user_id)->toBe($this->owner->id)
        ->and($video->file->path)->toBe('medias/talk.mp4');
});

test('create_video leaves no video for an unknown upload', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(CreateVideo::class, ['upload_id' => fake()->uuid(), 'name' => 'Dhamma talk'])
        ->assertHasErrors();

    expect(Video::query()->exists())->toBeFalse();
});
