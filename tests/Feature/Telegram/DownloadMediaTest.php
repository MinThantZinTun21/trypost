<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake();

    $this->workspace = Workspace::factory()->create();
    $this->media = Media::factory()->video()->for($this->workspace, 'mediable')->create([
        'path' => 'medias/launch.mp4',
        'original_filename' => 'Launch day.mp4',
    ]);
    Storage::put($this->media->path, 'video-bytes');
});

test('on remote storage a signed download link redirects to a short-lived URL that downloads the file', function () {
    config([
        'filesystems.default' => 's3',
        'filesystems.disks.s3' => [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'trypost-media',
            'endpoint' => 'https://storage.example.test',
            'use_path_style_endpoint' => true,
        ],
    ]);
    Storage::forgetDisk('s3');

    $response = $this->get(URL::signedRoute('media.download', ['media' => $this->media]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://storage.example.test/trypost-media/medias/launch.mp4?')
        ->and($query['X-Amz-Expires'])->toBe('1800')
        ->and($query['response-content-disposition'])->toStartWith('attachment;')
        ->and($query['response-content-disposition'])->toContain('Launch day.mp4');
});

test('a local disk streams the file as an attachment', function () {
    $response = $this->get(URL::signedRoute('media.download', ['media' => $this->media]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Disposition'))->toContain('Launch day.mp4')
        ->and($response->streamedContent())->toBe('video-bytes');
});

test('the download link needs no sign-in and never expires', function () {
    $url = URL::signedRoute('media.download', ['media' => $this->media]);

    $this->travel(5)->years();

    $this->assertGuest();
    $this->get($url)->assertOk();
});

test('an unsigned or tampered download link is refused', function () {
    $this->get(route('media.download', ['media' => $this->media]))->assertStatus(Response::HTTP_FORBIDDEN);

    $other = Media::factory()->video()->for($this->workspace, 'mediable')->create();
    $tampered = str_replace($this->media->id, $other->id, URL::signedRoute('media.download', ['media' => $this->media]));

    $this->get($tampered)->assertStatus(Response::HTTP_FORBIDDEN);
});

test('a download link to deleted media is not found', function () {
    $url = URL::signedRoute('media.download', ['media' => $this->media]);
    $this->media->delete();

    $this->get($url)->assertStatus(Response::HTTP_NOT_FOUND);
});
