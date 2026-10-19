<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\ChunkedCloudUploader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = mobileHeaders($this->owner);
    $this->task = Task::factory()->for($this->workspace)->create();
});

function fakeAttachmentCloud(string $key = 'medias/memo.m4a', bool $objectStorage = true): ChunkedCloudUploader
{
    $fake = Mockery::mock(ChunkedCloudUploader::class);
    $fake->shouldReceive('isObjectStorageDisk')->andReturn($objectStorage);
    $fake->shouldReceive('presignUpload')->andReturn([
        'key' => $key,
        'url' => "https://bucket.example.test/{$key}?X-Amz-Signature=abc",
        'headers' => ['Content-Type' => 'audio/mp4'],
    ]);
    app()->instance(ChunkedCloudUploader::class, $fake);

    return $fake;
}

function startAttachmentUpload(string $fileName = 'Memo.M4A', int $size = 4096): string
{
    return test()->postJson(route('mobile.v1.tasks.attachments.uploads.store', test()->task), ['file_name' => $fileName, 'size' => $size], test()->headers)
        ->assertOk()
        ->json('upload_id');
}

function attachToTask(Task $task, int $count): void
{
    Media::factory()->count($count)->for($task, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION]);
}

test('starting an upload returns a presigned put url', function () {
    fakeAttachmentCloud();

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => 'Memo.M4A', 'size' => 4096], $this->headers)
        ->assertOk()
        ->assertJsonStructure(['upload_id', 'url', 'headers', 'expires_in_seconds'])
        ->assertJsonPath('url', 'https://bucket.example.test/medias/memo.m4a?X-Amz-Signature=abc')
        ->assertJsonPath('headers', ['Content-Type' => 'audio/mp4']);
});

test('an upload is refused for another type or over its cap', function (string $fileName, int $size, string $field) {
    fakeAttachmentCloud()->shouldNotReceive('presignUpload');

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => $fileName, 'size' => $size], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'a pdf' => ['notes.pdf', 4096, 'file_name'],
    'an executable' => ['run.exe', 4096, 'file_name'],
    'a photo over 10 MB' => ['photo.jpg', 10 * 1024 * 1024 + 1, 'size'],
    'a video over 200 MB' => ['clip.mp4', 200 * 1024 * 1024 + 1, 'size'],
    'audio over 25 MB' => ['memo.mp3', 25 * 1024 * 1024 + 1, 'size'],
    'empty' => ['memo.m4a', 0, 'size'],
]);

test('the caps come from config', function () {
    fakeAttachmentCloud();
    config(['trypost.tasks.attachments.max_size_mb.audio' => 1]);

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => 'memo.m4a', 'size' => 1024 * 1024 + 1], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size' => __('assets.upload.file_too_large', ['max' => 1])]);
});

test('an upload is refused once the task holds ten attachments', function () {
    fakeAttachmentCloud()->shouldNotReceive('presignUpload');
    attachToTask($this->task, 10);

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => 'memo.m4a', 'size' => 4096], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_name' => __('tasks.attachments_full', ['max' => 10])]);
});

test('an upload is refused without object storage', function () {
    fakeAttachmentCloud(objectStorage: false);

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => 'memo.m4a', 'size' => 4096], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_name' => __('tasks.attachment_not_object_storage')]);
});

test('a finished audio upload is attached to the task', function () {
    $fake = fakeAttachmentCloud();
    $fake->shouldReceive('storedSize')->with('medias/memo.m4a')->andReturn(4096);
    $fake->shouldReceive('storedMimeType')->andReturn('audio/x-m4a');
    $uploadId = startAttachmentUpload();

    $response = $this->postJson(route('mobile.v1.tasks.attachments.store', $this->task), ['upload_id' => $uploadId], $this->headers)
        ->assertCreated()
        ->assertJsonPath('type', 'audio')
        ->assertJsonPath('mime_type', 'audio/x-m4a')
        ->assertJsonPath('size', 4096)
        ->assertJsonPath('file_name', 'memo.m4a');

    $attachment = Media::query()->sole();
    expect($response->json('id'))->toBe($attachment->id)
        ->and($attachment->mediable_id)->toBe($this->task->id)
        ->and($attachment->collection)->toBe(Task::ATTACHMENTS_COLLECTION)
        ->and($attachment->type)->toBe(MediaType::Audio)
        ->and($this->workspace->getMedia('assets')->exists())->toBeFalse();

    $this->getJson(route('mobile.v1.tasks.show', $this->task), $this->headers)
        ->assertJsonPath('attachments.0.id', $attachment->id)
        ->assertJsonPath('attachments.0.download_url', $response->json('download_url'));
});

test('a stored file of another type is refused and removed', function () {
    $fake = fakeAttachmentCloud();
    $fake->shouldReceive('storedSize')->andReturn(4096);
    $fake->shouldReceive('storedMimeType')->andReturn('application/pdf');
    Storage::put('medias/memo.m4a', 'pdf bytes');
    $uploadId = startAttachmentUpload();

    $this->postJson(route('mobile.v1.tasks.attachments.store', $this->task), ['upload_id' => $uploadId], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upload_id' => __('tasks.attachment_unsupported')]);

    expect(Media::query()->exists())->toBeFalse();
    Storage::assertMissing('medias/memo.m4a');
});

test('a stored file over its cap is refused and removed', function () {
    $fake = fakeAttachmentCloud('medias/clip.mp4');
    $fake->shouldReceive('storedSize')->andReturn(200 * 1024 * 1024 + 1);
    $fake->shouldReceive('storedMimeType')->andReturn('video/mp4');
    Storage::put('medias/clip.mp4', 'video bytes');
    $uploadId = startAttachmentUpload('clip.mp4');

    $this->postJson(route('mobile.v1.tasks.attachments.store', $this->task), ['upload_id' => $uploadId], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upload_id' => __('assets.upload.file_too_large', ['max' => 200])]);

    Storage::assertMissing('medias/clip.mp4');
});

test('an eleventh attachment is refused when it finishes', function () {
    $fake = fakeAttachmentCloud();
    $fake->shouldReceive('storedSize')->never();
    $uploadId = startAttachmentUpload();
    attachToTask($this->task, 10);

    $this->postJson(route('mobile.v1.tasks.attachments.store', $this->task), ['upload_id' => $uploadId], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['upload_id' => __('tasks.attachments_full', ['max' => 10])]);

    expect($this->task->attachments()->count())->toBe(10);
});

test('attachments are listed oldest first with download links', function () {
    $first = Media::factory()->audio()->for($this->task, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION, 'created_at' => now()->subHour(), 'original_filename' => 'memo.m4a']);
    $second = Media::factory()->video()->for($this->task, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION, 'meta' => ['duration' => 12.5]]);
    Storage::put($first->path, 'audio bytes');

    $response = $this->getJson(route('mobile.v1.tasks.show', $this->task), $this->headers)
        ->assertOk()
        ->assertJsonPath('attachments.*.id', [$first->id, $second->id])
        ->assertJsonPath('attachments.0.type', 'audio')
        ->assertJsonPath('attachments.0.file_name', 'memo.m4a')
        ->assertJsonPath('attachments.0.duration', null)
        ->assertJsonPath('attachments.1.duration', 12.5);

    $this->get($response->json('attachments.0.download_url'))->assertOk()->assertDownload();
});

test('deleting an attachment removes its file', function () {
    $attachment = Media::factory()->audio()->for($this->task, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION]);
    Storage::put($attachment->path, 'audio bytes');

    $this->deleteJson(route('mobile.v1.tasks.attachments.destroy', [$this->task, $attachment]), [], $this->headers)->assertNoContent();

    expect(Media::query()->exists())->toBeFalse();
    Storage::assertMissing($attachment->path);
});

test('only the task\'s own attachments can be deleted through it', function () {
    $otherTask = Task::factory()->for($this->workspace)->create();
    $otherAttachment = Media::factory()->for($otherTask, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION]);
    $asset = Media::factory()->assets()->for($this->workspace, 'mediable')->create();

    $this->deleteJson(route('mobile.v1.tasks.attachments.destroy', [$this->task, $otherAttachment]), [], $this->headers)->assertNotFound();
    $this->deleteJson(route('mobile.v1.tasks.attachments.destroy', [$this->task, $asset]), [], $this->headers)->assertNotFound();

    expect(Media::query()->count())->toBe(2);
});

test('deleting the task deletes its attachments and their files', function () {
    $attachment = Media::factory()->video()->for($this->task, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION]);
    Storage::put($attachment->path, 'video bytes');

    $this->deleteJson(route('mobile.v1.tasks.destroy', $this->task), [], $this->headers)->assertNoContent();

    expect(Media::query()->exists())->toBeFalse();
    Storage::assertMissing($attachment->path);
});

test('another workspace\'s task takes no attachments', function () {
    fakeAttachmentCloud()->shouldNotReceive('presignUpload');
    $foreignTask = Task::factory()->create();
    $attachment = Media::factory()->for($foreignTask, 'mediable')->create(['collection' => Task::ATTACHMENTS_COLLECTION]);

    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $foreignTask), ['file_name' => 'memo.m4a', 'size' => 4096], $this->headers)->assertNotFound();
    $this->postJson(route('mobile.v1.tasks.attachments.store', $foreignTask), ['upload_id' => fake()->uuid()], $this->headers)->assertNotFound();
    $this->deleteJson(route('mobile.v1.tasks.attachments.destroy', [$foreignTask, $attachment]), [], $this->headers)->assertNotFound();
});

test('attachments need the mobile token', function () {
    $this->postJson(route('mobile.v1.tasks.attachments.uploads.store', $this->task), ['file_name' => 'memo.m4a', 'size' => 4096])->assertUnauthorized();
});
