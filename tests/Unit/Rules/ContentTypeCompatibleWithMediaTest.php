<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Rules\ContentTypeCompatibleWithMedia;

function runMediaRule(string $contentType, array $media): array
{
    $errors = [];
    $rule = (new ContentTypeCompatibleWithMedia)->setData(['media' => $media]);
    $rule->validate('platforms.0.content_type', $contentType, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

test('passes when content type does not require media and none provided', function () {
    expect(runMediaRule(ContentType::FacebookPost->value, []))->toBe([]);
});

test('fails when content type requires media and none provided', function () {
    $errors = runMediaRule(ContentType::FacebookReel->value, []);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('requires at least one image or video');
});

test('fails when content type does not support images and an image is present', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::TikTokVideo->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('accepts only videos');
});

test('fails when content type does not support video and a video is present', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4']];

    $errors = runMediaRule(ContentType::TikTokPhoto->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not accept videos');
});

test('youtube short rejects images', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::YouTubeShort->value, $media);

    expect($errors[0])->toContain('accepts only videos');
});

test('passes when image-only content type receives an image', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/png']];

    expect(runMediaRule(ContentType::TikTokPhoto->value, $media))->toBe([]);
});

test('passes when video-only content type receives a video', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4']];

    expect(runMediaRule(ContentType::TikTokVideo->value, $media))->toBe([]);
    expect(runMediaRule(ContentType::YouTubeShort->value, $media))->toBe([]);
    expect(runMediaRule(ContentType::FacebookReel->value, $media))->toBe([]);
    expect(runMediaRule(ContentType::FacebookStory->value, $media))->toBe([]);
});

test('facebook story rejects images', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::FacebookStory->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('accepts only videos');
});

test('detects media type from mime when type field is missing', function () {
    $media = [['mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::TikTokVideo->value, $media);

    expect($errors)->toHaveCount(1);
});

test('detects media type from the filename when both type and mime are missing', function () {
    // Same fallback as MediaItem::fromArray(): a video is measured against the video cap, not the image one.
    $overVideoCap = ContentType::TikTokVideo->maxVideoBytes() + 1;

    $byPath = runMediaRule(ContentType::TikTokVideo->value, [['path' => 'medias/clip.mp4', 'size' => $overVideoCap]]);
    $byName = runMediaRule(ContentType::TikTokVideo->value, [['original_filename' => 'Clip.MOV', 'size' => $overVideoCap]]);
    $imageOnly = runMediaRule(ContentType::TikTokVideo->value, [['path' => 'medias/photo.png']]);

    expect($byPath)->toHaveCount(1)->and($byPath[0])->toContain('Video exceeds')
        ->and($byName)->toHaveCount(1)->and($byName[0])->toContain('Video exceeds')
        ->and($imageOnly)->toHaveCount(1)->and($imageOnly[0])->toContain('accepts only videos');
});

test('an explicit type wins over a contradicting mime', function () {
    // Mirrors classify() in mediaType.ts: the server-assigned type is trusted first.
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'video/mp4']];

    expect(runMediaRule(ContentType::TikTokVideo->value, $media))->toHaveCount(1)
        ->and(runMediaRule(ContentType::TikTokPhoto->value, $media))->toBe([]);
});

test('every content type accepts a mov video', function (ContentType $type) {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/quicktime']];

    expect(runMediaRule($type->value, $media))->toBe([]);
})->with([
    ContentType::FacebookPost,
    ContentType::FacebookReel,
    ContentType::TikTokVideo,
    ContentType::YouTubeShort,
]);

test('a gif is rejected on every content type', function (ContentType $type) {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/gif']];

    $errors = runMediaRule($type->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not accept GIF');
})->with([
    ContentType::FacebookPost,
    ContentType::TikTokPhoto,
]);

test('an image over the content type cap is rejected by size', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg', 'size' => 4 * 1024 * 1024 + 1]];

    $errors = runMediaRule(ContentType::FacebookPost->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('Image exceeds the');
    expect($errors[0])->toContain('4 MB');
});

test('a kind violation is reported alone and is not overwritten by a size violation on the same item', function () {
    // A 6 MB GIF on Facebook breaks two rules; the root cause ("does not accept GIF") must be the one message.
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/gif', 'size' => 6 * 1024 * 1024]];

    $errors = runMediaRule(ContentType::FacebookPost->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('GIF');
});

test('an item that cannot be classified is not measured against any cap', function () {
    // An API `url`-only entry before download: no type, mime or filename, but a stray size.
    $media = [['url' => 'https://example.com/asset', 'size' => 999_999_999]];

    expect(runMediaRule(ContentType::FacebookPost->value, $media))->toBe([]);
});

test('binary caps keep binary units', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg', 'size' => 5 * 1024 * 1024]];

    $errors = runMediaRule(ContentType::FacebookPost->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('4 MB limit for this post type (yours is 5.0 MB)');
});

test('an image exactly at the content type cap passes', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg', 'size' => 4 * 1024 * 1024]];

    expect(runMediaRule(ContentType::FacebookPost->value, $media))->toBe([]);
});

test('a video over the content type cap is rejected by size', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4', 'size' => ContentType::FacebookReel->maxVideoBytes() + 1]];

    $errors = runMediaRule(ContentType::FacebookReel->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('Video exceeds the');
});

test('media without a size is not checked against byte caps', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4']];

    expect(runMediaRule(ContentType::FacebookReel->value, $media))->toBe([]);
});

test('a video longer than the content type cap is rejected by duration', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4', 'meta' => ['duration' => 61.4]]];

    $errors = runMediaRule(ContentType::FacebookStory->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('allows up to 1min');
    expect($errors[0])->toContain('Video is 1min 2s long');
});

test('a video within the duration cap passes and a video without duration is not checked', function () {
    $within = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4', 'meta' => ['duration' => 60]]];
    $unknown = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4', 'meta' => []]];

    expect(runMediaRule(ContentType::FacebookStory->value, $within))->toBe([]);
    expect(runMediaRule(ContentType::FacebookStory->value, $unknown))->toBe([]);
});

test('a mixed-media content type accepts an image and a video together', function () {
    $media = [
        ['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg'],
        ['type' => MediaType::Video->value, 'mime_type' => 'video/mp4'],
    ];

    expect(runMediaRule(ContentType::FacebookPost->value, $media))->toBe([]);
});

test('a pdf is rejected on every content type', function (ContentType $type) {
    $media = [['type' => MediaType::Document->value, 'mime_type' => 'application/pdf']];

    $errors = runMediaRule($type->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not accept PDF documents');
})->with([
    ContentType::FacebookPost,
    ContentType::TikTokPhoto,
]);

test('falls back to stored media when the request omits the media key', function () {
    $errors = [];
    (new ContentTypeCompatibleWithMedia([['type' => 'image', 'mime_type' => 'image/jpeg']]))
        ->setData([]) // no 'media' key in the request -> use the fallback
        ->validate('platforms.0.content_type', ContentType::TikTokVideo->value, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('accepts only videos');
});

test('request media takes precedence over the stored fallback', function () {
    // Fallback is an image (would fail on a video-only type), but the request
    // carries a video, which passes — proving the request media is used.
    $errors = [];
    (new ContentTypeCompatibleWithMedia([['type' => 'image', 'mime_type' => 'image/jpeg']]))
        ->setData(['media' => [
            ['type' => 'video', 'mime_type' => 'video/mp4'],
        ]])
        ->validate('platforms.0.content_type', ContentType::TikTokVideo->value, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

    expect($errors)->toBe([]);
});

test('does nothing for invalid content type values', function () {
    expect(runMediaRule('not_a_real_content_type', []))->toBe([]);
});
