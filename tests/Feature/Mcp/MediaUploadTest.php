<?php

declare(strict_types=1);

use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CompleteMediaUpload;
use App\Mcp\Tools\StartMediaUpload;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Cache::flush();
    config(['filesystems.default' => 'r2', 'filesystems.disks.r2.driver' => 's3']);
    Storage::fake('r2');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

function fakeMcpUploadCloud(bool $objectStorage = true): ChunkedCloudUploader
{
    $fake = Mockery::mock(ChunkedCloudUploader::class);
    $fake->shouldReceive('isObjectStorageDisk')->andReturn($objectStorage);
    $fake->shouldReceive('presignUpload')->andReturn([
        'key' => 'medias/clip.mp4',
        'url' => 'https://bucket.example.test/medias/clip.mp4?X-Amz-Signature=abc',
        'headers' => ['Content-Type' => 'video/mp4'],
    ]);
    app()->instance(ChunkedCloudUploader::class, $fake);

    return $fake;
}

function startMcpUpload(User $user, string $fileName = 'Clip.MP4', int $size = 2048): string
{
    $uploadId = null;

    SchedulerServer::actingAs($user)
        ->tool(StartMediaUpload::class, ['file_name' => $fileName, 'size' => $size])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$uploadId) {
            $uploadId = $json->toArray()['upload_id'];
            $json->etc();
        });

    return $uploadId;
}

test('start_media_upload returns a signed put url and a ready curl command', function () {
    fakeMcpUploadCloud();

    SchedulerServer::actingAs($this->owner)
        ->tool(StartMediaUpload::class, ['file_name' => 'Clip.MP4', 'size' => 2048])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->whereType('upload_id', 'string')
            ->where('method', 'PUT')
            ->where('url', 'https://bucket.example.test/medias/clip.mp4?X-Amz-Signature=abc')
            ->where('headers', ['Content-Type' => 'video/mp4'])
            ->where('expires_in_seconds', 3600)
            ->where('curl', "curl --fail -X PUT -T '/path/to/clip.mp4' -H 'Content-Type: video/mp4' 'https://bucket.example.test/medias/clip.mp4?X-Amz-Signature=abc'"));
});

test('start_media_upload is refused when the disk is not object storage', function () {
    fakeMcpUploadCloud(objectStorage: false);

    SchedulerServer::actingAs($this->owner)
        ->tool(StartMediaUpload::class, ['file_name' => 'clip.mp4', 'size' => 2048])
        ->assertHasErrors([__('mcp.upload.not_object_storage')]);
});

test('start_media_upload checks the file like the composer does', function (string $fileName, int $size) {
    fakeMcpUploadCloud()->shouldNotReceive('presignUpload');

    SchedulerServer::actingAs($this->owner)
        ->tool(StartMediaUpload::class, ['file_name' => $fileName, 'size' => $size])
        ->assertHasErrors();
})->with([
    'unsupported extension' => ['notes.exe', 2048],
    'too large' => ['clip.mp4', PHP_INT_MAX],
    'empty' => ['clip.mp4', 0],
]);

test('complete_media_upload registers the video and returns the media item', function () {
    $fake = fakeMcpUploadCloud();
    $fake->shouldReceive('storedSize')->with('medias/clip.mp4')->andReturn(2048);
    $fake->shouldReceive('storedMimeType')->andReturn('video/mp4');
    $fake->shouldReceive('readRange')->andThrow(new RuntimeException('range GET failed'));
    $uploadId = startMcpUpload($this->owner);

    SchedulerServer::actingAs($this->owner)
        ->tool(CompleteMediaUpload::class, ['upload_id' => $uploadId, 'duration' => 42.5])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('media', fn (AssertableJson $media) => $media
                ->where('id', $this->workspace->getMedia('assets')->first()->id)
                ->where('type', 'video')
                ->whereType('url', 'string')
                ->where('mime_type', 'video/mp4')
                ->where('size', 2048)
                ->where('duration', 42.5)));

    expect($this->workspace->getMedia('assets')->first()->path)->toBe('medias/clip.mp4');
});

test('complete_media_upload rejects an upload that cannot be completed', function (Closure $uploadId) {
    fakeMcpUploadCloud()->shouldReceive('storedSize')->andReturn(null);

    SchedulerServer::actingAs($this->owner)
        ->tool(CompleteMediaUpload::class, ['upload_id' => $uploadId($this)])
        ->assertHasErrors();

    expect($this->workspace->getMedia('assets')->exists())->toBeFalse();
})->with([
    'unknown' => [fn () => fake()->uuid()],
    'not a uuid' => [fn () => 'clip.mp4'],
    'another user\'s' => [fn ($test) => startMcpUpload(User::factory()->create())],
    'never uploaded' => [fn ($test) => startMcpUpload($test->owner)],
]);

test('complete_media_upload rejects a file of an unsupported type', function () {
    $fake = fakeMcpUploadCloud();
    $fake->shouldReceive('storedSize')->andReturn(2048);
    $fake->shouldReceive('storedMimeType')->andReturn('application/x-msdownload');
    $uploadId = startMcpUpload($this->owner);

    SchedulerServer::actingAs($this->owner)
        ->tool(CompleteMediaUpload::class, ['upload_id' => $uploadId])
        ->assertHasErrors([__('assets.upload.unsupported')]);
});
