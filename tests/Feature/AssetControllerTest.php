<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake();

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('chunked upload completes with single chunk', function () {
    // Use real PNG bytes so mime_content_type detects image/png. The MIME
    // is sniffed from content magic bytes, not the X-File-Name header.
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');
    $size = strlen($content);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => 'test.png',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    $response->assertJsonStructure(['done', 'id', 'path', 'url', 'type', 'mime_type', 'original_filename', 'size']);
    expect($this->workspace->getMedia('assets')->count())->toBe(1);
});

test('chunked upload completes for a pdf document', function () {
    // Real %PDF magic bytes so mime_content_type detects application/pdf.
    $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    $size = strlen($content);

    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => 'deck.pdf',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    );

    $response->assertSuccessful();
    $response->assertJson(['done' => true]);
    expect($this->workspace->getMedia('assets')->first()->type->value)->toBe('document');
});

test('chunked upload reports progress on intermediate chunks', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-499/1000',
            'HTTP_X_FILE_NAME' => 'test-video.mp4',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('a', 500),
    );

    $response->assertSuccessful();
    $response->assertJson(['done' => false, 'progress' => 50]);
    expect(Media::count())->toBe(0);
});

test('chunked upload rejects unsupported file extension', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-99/100',
            'HTTP_X_FILE_NAME' => 'malware.exe',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 100),
    );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('file_name');
});

test('chunked upload rejects invalid Content-Range header', function () {
    $response = $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'invalid',
            'HTTP_X_FILE_NAME' => 'test.jpg',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        'data',
    );

    // FormRequest validation surfaces parse failures as 422
    // (range_start / range_end / total_size all required).
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['range_start', 'range_end', 'total_size']);
});

test('chunked upload rejects unauthenticated', function () {
    $response = $this->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-99/100',
            'HTTP_X_FILE_NAME' => 'test.jpg',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 100),
    );

    $response->assertUnauthorized();
});
