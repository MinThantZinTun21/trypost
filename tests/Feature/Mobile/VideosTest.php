<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    config(['trypost.mobile.token' => 'mobile-test-token']);
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = ['Authorization' => 'Bearer mobile-test-token'];
});

function fakeVideoUploadCloud(bool $objectStorage = true): ChunkedCloudUploader
{
    $fake = Mockery::mock(ChunkedCloudUploader::class);
    $fake->shouldReceive('isObjectStorageDisk')->andReturn($objectStorage);
    $fake->shouldReceive('presignUpload')->andReturn([
        'key' => 'medias/raw.mp4',
        'url' => 'https://bucket.example.test/medias/raw.mp4?X-Amz-Signature=abc',
        'headers' => ['Content-Type' => 'video/mp4'],
    ]);
    app()->instance(ChunkedCloudUploader::class, $fake);

    return $fake;
}

function startVideoUpload(string $fileName = 'Raw.MP4', int $size = 4096): string
{
    return test()->postJson(route('mobile.v1.videos.uploads.store'), ['file_name' => $fileName, 'size' => $size], test()->headers)
        ->assertOk()
        ->json('upload_id');
}

test('starting an upload returns a presigned put url', function () {
    fakeVideoUploadCloud();

    $this->postJson(route('mobile.v1.videos.uploads.store'), ['file_name' => 'Raw.MP4', 'size' => 4096], $this->headers)
        ->assertOk()
        ->assertJsonStructure(['upload_id', 'url', 'headers', 'expires_in_seconds'])
        ->assertJsonPath('url', 'https://bucket.example.test/medias/raw.mp4?X-Amz-Signature=abc')
        ->assertJsonPath('headers', ['Content-Type' => 'video/mp4']);
});

test('an upload is refused for a file that is not a video or too large', function (string $fileName, int $size, string $field) {
    fakeVideoUploadCloud()->shouldNotReceive('presignUpload');

    $this->postJson(route('mobile.v1.videos.uploads.store'), ['file_name' => $fileName, 'size' => $size], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'a photo' => ['photo.jpg', 4096, 'file_name'],
    'a pdf' => ['notes.pdf', 4096, 'file_name'],
    'too large' => ['raw.mp4', PHP_INT_MAX, 'size'],
    'empty' => ['raw.mp4', 0, 'size'],
]);

test('an upload is refused without object storage', function () {
    fakeVideoUploadCloud(objectStorage: false);

    $this->postJson(route('mobile.v1.videos.uploads.store'), ['file_name' => 'raw.mp4', 'size' => 4096], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_name' => __('videos.not_object_storage')]);
});

test('a finished upload is registered as a video with its name', function () {
    $fake = fakeVideoUploadCloud();
    $fake->shouldReceive('storedSize')->with('medias/raw.mp4')->andReturn(4096);
    $fake->shouldReceive('storedMimeType')->andReturn('video/mp4');
    $fake->shouldReceive('readRange')->andThrow(new RuntimeException('range GET failed'));
    $uploadId = startVideoUpload();

    $response = $this->postJson(route('mobile.v1.videos.store'), ['upload_id' => $uploadId, 'name' => 'Beach day'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('name', 'Beach day')
        ->assertJsonPath('size', 4096);

    $video = Video::query()->sole();
    expect($response->json('id'))->toBe($video->id)
        ->and($video->workspace_id)->toBe($this->workspace->id)
        ->and($video->user_id)->toBe($this->owner->id)
        ->and($video->file->path)->toBe('medias/raw.mp4')
        ->and($this->workspace->getMedia('assets')->exists())->toBeFalse();
});

test('an unknown upload is not registered', function () {
    fakeVideoUploadCloud()->shouldReceive('storedSize')->andReturn(null);

    $this->postJson(route('mobile.v1.videos.store'), ['upload_id' => fake()->uuid(), 'name' => 'Beach day'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('upload_id');

    expect(Video::query()->exists())->toBeFalse();
});

test('a stored file that is not a video is refused and removed', function () {
    $fake = fakeVideoUploadCloud();
    $fake->shouldReceive('storedSize')->andReturn(4096);
    $fake->shouldReceive('storedMimeType')->andReturn('image/jpeg');
    Storage::put('medias/raw.mp4', 'jpeg bytes');
    $uploadId = startVideoUpload();

    $this->postJson(route('mobile.v1.videos.store'), ['upload_id' => $uploadId, 'name' => 'Beach day'], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upload_id' => __('videos.not_a_video')]);

    expect(Video::query()->exists())->toBeFalse()
        ->and(Media::query()->exists())->toBeFalse();
    Storage::assertMissing('medias/raw.mp4');
});

test('a video needs a name', function () {
    fakeVideoUploadCloud()->shouldNotReceive('storedSize');

    $this->postJson(route('mobile.v1.videos.store'), ['upload_id' => fake()->uuid(), 'name' => ''], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('videos are listed newest first', function () {
    $older = Video::factory()->for($this->workspace)->withFile(size: 1000, duration: 12.5)->create(['name' => 'Older', 'created_at' => now()->subDay()]);
    $newer = Video::factory()->for($this->workspace)->withFile()->create(['name' => 'Newer']);
    Video::factory()->withFile()->create(['name' => 'Someone else\'s']);

    $this->getJson(route('mobile.v1.videos.index'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonPath('data.1.name', 'Older')
        ->assertJsonPath('data.1.size', 1000)
        ->assertJsonPath('data.1.duration', 12.5)
        ->assertJsonPath('data.1.created_at', $older->created_at->utc()->toIso8601String())
        ->assertJsonPath('meta.per_page', (int) config('app.pagination.default'));
});

test('deleting a video removes it and its file', function () {
    $video = Video::factory()->for($this->workspace)->withFile()->create();
    $path = $video->file->path;
    Storage::put($path, 'video bytes');

    $this->deleteJson(route('mobile.v1.videos.destroy', $video), [], $this->headers)->assertNoContent();

    expect(Video::query()->exists())->toBeFalse()
        ->and(Media::query()->exists())->toBeFalse();
    Storage::assertMissing($path);
});

test('another workspace\'s video is not deleted', function () {
    $video = Video::factory()->withFile()->create();

    $this->deleteJson(route('mobile.v1.videos.destroy', $video), [], $this->headers)->assertNotFound();

    expect($video->fresh())->not->toBeNull();
});

test('videos need the token', function () {
    $this->getJson(route('mobile.v1.videos.index'))->assertUnauthorized();
});
