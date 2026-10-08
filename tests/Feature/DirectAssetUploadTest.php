<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Aws\Command;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Cache::flush();
    config(['filesystems.default' => 'r2', 'filesystems.disks.r2.driver' => 's3']);
    Storage::fake('r2');
    seedDirectUploadWorkspace();
});

function seedDirectUploadWorkspace(): void
{
    test()->account = Account::factory()->create();
    test()->user = User::factory()->create(['account_id' => test()->account->id]);
    test()->account->update(['owner_id' => test()->user->id]);
    test()->workspace = Workspace::factory()->create([
        'account_id' => test()->account->id,
        'user_id' => test()->user->id,
    ]);
    test()->workspace->members()->attach(test()->user->id, ['role' => Role::Member->value]);
    test()->user->update(['current_workspace_id' => test()->workspace->id]);
}

function fakeDirectUploadCloud(string $key = 'medias/clip.mp4'): ChunkedCloudUploader
{
    $fake = Mockery::mock(ChunkedCloudUploader::class);
    $fake->shouldReceive('isObjectStorageDisk')->andReturn(true);
    $fake->shouldReceive('presignUpload')->andReturn([
        'key' => $key,
        'url' => "https://bucket.example.test/{$key}?X-Amz-Signature=abc",
        'headers' => ['Content-Type' => 'video/mp4'],
    ]);
    app()->instance(ChunkedCloudUploader::class, $fake);

    return $fake;
}

function startDirectUpload(string $fileName, int $totalSize = 1024, ?string $uploadId = null): TestResponse
{
    return test()->actingAs(test()->user)->postJson(route('app.assets.store-direct'), [
        'file_name' => $fileName,
        'total_size' => $totalSize,
        'upload_id' => $uploadId ?? Str::uuid()->toString(),
    ]);
}

function completeDirectUpload(string $uploadId, ?string $duration = null): TestResponse
{
    return test()->actingAs(test()->user)->postJson(route('app.assets.complete-direct'), array_filter([
        'upload_id' => $uploadId,
        'duration' => $duration,
    ]));
}

test('direct upload is declined when the disk is not object storage', function () {
    config(['filesystems.default' => 'local']);

    startDirectUpload('clip.mp4')->assertOk()->assertExactJson(['direct' => false]);
});

test('direct upload hands out a presigned url for object storage', function () {
    fakeDirectUploadCloud();

    startDirectUpload('Clip.MP4')->assertOk()->assertJson([
        'direct' => true,
        'url' => 'https://bucket.example.test/medias/clip.mp4?X-Amz-Signature=abc',
        'headers' => ['Content-Type' => 'video/mp4'],
    ]);
});

test('direct upload rejects a file type or size the app does not accept', function (string $fileName, int $totalSize, string $field) {
    fakeDirectUploadCloud();

    startDirectUpload($fileName, $totalSize)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'executable' => ['payload.exe', 1024, 'file_name'],
    'too large' => ['clip.mp4', 1025 * 1024 * 1024, 'total_size'],
    'empty' => ['clip.mp4', 0, 'total_size'],
]);

test('completing a direct upload registers the stored video with its probed duration', function () {
    $bytes = file_get_contents(base_path('tests/fixtures/sample.mp4'));
    $fake = fakeDirectUploadCloud();
    $fake->shouldReceive('storedSize')->with('medias/clip.mp4')->andReturn(strlen($bytes));
    $fake->shouldReceive('storedMimeType')->with('medias/clip.mp4', strlen($bytes))->andReturn('video/mp4');
    $fake->shouldReceive('readRange')
        ->andReturnUsing(fn (string $key, int $offset, int $length) => substr($bytes, $offset, $length));
    $uploadId = Str::uuid()->toString();

    startDirectUpload('clip.mp4', strlen($bytes), $uploadId)->assertOk();

    completeDirectUpload($uploadId, '600')->assertOk()->assertJson([
        'done' => true,
        'type' => 'video',
        'path' => 'medias/clip.mp4',
    ]);

    $media = test()->workspace->getMedia('assets')->first();
    expect($media->size)->toBe(strlen($bytes))
        ->and($media->meta)->toEqual(['duration' => 1.0]);
});

test('completing a direct upload keeps the browser duration when the stored video cannot be probed', function () {
    $fake = fakeDirectUploadCloud();
    $fake->shouldReceive('storedSize')->andReturn(2048);
    $fake->shouldReceive('storedMimeType')->andReturn('video/mp4');
    $fake->shouldReceive('readRange')->andThrow(new RuntimeException('range GET failed'));
    $uploadId = Str::uuid()->toString();

    startDirectUpload('clip.mp4', 2048, $uploadId)->assertOk();
    completeDirectUpload($uploadId, '42.5')->assertOk();

    expect(test()->workspace->getMedia('assets')->first()->meta)->toEqual(['duration' => 42.5]);
});

test('completing a direct image upload runs it through the image pipeline and drops the original', function () {
    Storage::disk('r2')->put('medias/photo.png', 'original');
    $png = file_get_contents(base_path('tests/fixtures/1x1.png'));
    $fake = fakeDirectUploadCloud('medias/photo.png');
    $fake->shouldReceive('storedSize')->andReturn(strlen($png));
    $fake->shouldReceive('storedMimeType')->andReturn('image/png');
    $fake->shouldReceive('download')
        ->with('medias/photo.png', Mockery::type('string'))
        ->andReturnUsing(fn (string $key, string $localPath) => file_put_contents($localPath, $png));
    $uploadId = Str::uuid()->toString();

    startDirectUpload('photo.png', strlen($png), $uploadId)->assertOk();
    completeDirectUpload($uploadId)->assertOk()->assertJson(['done' => true, 'type' => 'image']);

    $media = test()->workspace->getMedia('assets')->first();
    Storage::disk('r2')->assertMissing('medias/photo.png');
    Storage::disk('r2')->assertExists($media->path);
});

test('completing an upload that was never started is rejected', function () {
    fakeDirectUploadCloud()->shouldNotReceive('storedSize');

    completeDirectUpload(Str::uuid()->toString())->assertUnprocessable()->assertJsonValidationErrors('upload_id');

    expect(test()->workspace->getMedia('assets')->exists())->toBeFalse();
});

test('completing another user\'s upload is rejected', function () {
    fakeDirectUploadCloud()->shouldNotReceive('storedSize');
    $uploadId = Str::uuid()->toString();

    startDirectUpload('clip.mp4', 1024, $uploadId)->assertOk();
    seedDirectUploadWorkspace();

    completeDirectUpload($uploadId)->assertUnprocessable()->assertJsonValidationErrors('upload_id');
});

test('completing an upload whose object never reached the bucket is rejected', function () {
    fakeDirectUploadCloud()->shouldReceive('storedSize')->andReturn(null);
    $uploadId = Str::uuid()->toString();

    startDirectUpload('clip.mp4', 1024, $uploadId)->assertOk();

    completeDirectUpload($uploadId)->assertUnprocessable()->assertJsonValidationErrors('upload_id');
});

test('completing an upload whose bytes are not an accepted type deletes the object', function (string $mimeType, int $size) {
    Storage::disk('r2')->put('medias/clip.mp4', 'stored');
    $fake = fakeDirectUploadCloud();
    $fake->shouldReceive('storedSize')->andReturn($size);
    $fake->shouldReceive('storedMimeType')->andReturn($mimeType);
    $uploadId = Str::uuid()->toString();

    startDirectUpload('clip.mp4', 1024, $uploadId)->assertOk();

    completeDirectUpload($uploadId)->assertUnprocessable()->assertJsonValidationErrors('upload_id');
    Storage::disk('r2')->assertMissing('medias/clip.mp4');
    expect(test()->workspace->getMedia('assets')->exists())->toBeFalse();
})->with([
    'unsupported bytes' => ['application/x-msdownload', 1024],
    'image over the image limit' => ['image/png', 11 * 1024 * 1024],
]);

test('the cloud uploader presigns a put for a fresh media key', function () {
    $client = new S3Client([
        'region' => 'auto',
        'version' => 'latest',
        'endpoint' => 'https://account.r2.example.test',
        'use_path_style_endpoint' => true,
        'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
    ]);
    $uploader = new ChunkedCloudUploader(Cache::store(), $client, 'test-bucket', 'r2');

    $upload = $uploader->presignUpload('clip.mp4');

    expect($upload['key'])->toStartWith('medias/')->toEndWith('.mp4')
        ->and($upload['url'])->toStartWith("https://account.r2.example.test/test-bucket/{$upload['key']}?")
        ->toContain('X-Amz-Signature=')
        ->and($upload['headers'])->toBe(['Content-Type' => 'video/mp4']);
});

test('the cloud uploader reports no size for a missing object', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('headObject')->andThrow(new S3Exception(
        'Not Found',
        new Command('HeadObject'),
        ['response' => new PsrResponse(404)],
    ));
    $uploader = new ChunkedCloudUploader(Cache::store(), $client, 'test-bucket', 'r2');

    expect($uploader->storedSize('medias/missing.mp4'))->toBeNull();
});

test('the cloud uploader reads the stored size and sniffs the stored type', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('headObject')->andReturn(new Result(['ContentLength' => 2048]));
    $client->shouldReceive('getObject')
        ->with(['Bucket' => 'test-bucket', 'Key' => 'medias/photo.mp4', 'Range' => 'bytes=0-2047'])
        ->andReturn(new Result(['Body' => file_get_contents(base_path('tests/fixtures/1x1.png'))]));
    $uploader = new ChunkedCloudUploader(Cache::store(), $client, 'test-bucket', 'r2');

    expect($uploader->storedSize('medias/photo.mp4'))->toBe(2048)
        ->and($uploader->storedMimeType('medias/photo.mp4', 2048))->toBe('image/png');
});
