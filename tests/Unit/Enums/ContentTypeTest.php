<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;

test('content type has correct labels', function () {
    expect(ContentType::FacebookPost->label())->toBe('Post');
    expect(ContentType::FacebookReel->label())->toBe('Reel');
    expect(ContentType::FacebookStory->label())->toBe('Story');
    expect(ContentType::YouTubeShort->label())->toBe('Short');
    expect(ContentType::TikTokVideo->label())->toBe('Video');
    expect(ContentType::TikTokPhoto->label())->toBe('Photo carousel');
});

test('content type has correct descriptions', function () {
    expect(ContentType::FacebookReel->description())->toContain('90 seconds');
    expect(ContentType::YouTubeShort->description())->toContain('3 minutes');
    expect(ContentType::TikTokVideo->description())->toContain('video');
});

test('every content type has a translated description', function (ContentType $type) {
    expect($type->description())
        ->not->toBe('')
        ->not->toBe("posts.content_types.{$type->value}.description");
})->with(ContentType::cases());

test('content type exposes max video duration in seconds', function () {
    expect(ContentType::FacebookPost->maxVideoDurationSec())->toBe(240 * 60);
    expect(ContentType::FacebookReel->maxVideoDurationSec())->toBe(90);
    expect(ContentType::FacebookStory->maxVideoDurationSec())->toBe(60);
    expect(ContentType::YouTubeShort->maxVideoDurationSec())->toBe(3 * 60);
    expect(ContentType::TikTokVideo->maxVideoDurationSec())->toBe(10 * 60);
    expect(ContentType::TikTokPhoto->maxVideoDurationSec())->toBeNull();
});

test('media rules for frontend expose the full editor rule set keyed by content type', function () {
    $rules = ContentType::mediaRulesForFrontend();

    expect($rules)->toHaveCount(count(ContentType::cases()));

    expect($rules['facebook_reel'])->toMatchArray([
        'max_files' => 1,
        'accept_images' => false,
        'accept_videos' => true,
        'requires_media' => true,
        'max_video_duration_sec' => 90,
        'aspect_ratio_min' => 0.5,
        'aspect_ratio_max' => 0.6,
    ]);

    expect($rules['tiktok_video']['max_video_duration_sec'])->toBe(10 * 60);
    expect($rules['tiktok_photo']['min_files'])->toBe(1);
    expect($rules['facebook_post']['requires_media'])->toBeFalse();
    expect($rules['youtube_short']['accepts_mov'])->toBeTrue();
});

test('media rules reuse enum capability helpers', function () {
    $rules = ContentType::FacebookStory->mediaRules();

    expect($rules['accept_images'])->toBe(ContentType::FacebookStory->supportsImage())
        ->and($rules['accept_videos'])->toBe(ContentType::FacebookStory->supportsVideo())
        ->and($rules['auto_fits_image'])->toBeFalse()
        ->and($rules['max_files'])->toBe(ContentType::FacebookStory->maxMediaCount());
});

test('content type maps to correct platform', function () {
    expect(ContentType::FacebookPost->platform())->toBe(Platform::Facebook);
    expect(ContentType::FacebookReel->platform())->toBe(Platform::Facebook);
    expect(ContentType::FacebookStory->platform())->toBe(Platform::Facebook);
    expect(ContentType::TikTokVideo->platform())->toBe(Platform::TikTok);
    expect(ContentType::TikTokPhoto->platform())->toBe(Platform::TikTok);
    expect(ContentType::YouTubeShort->platform())->toBe(Platform::YouTube);
});

test('content type has correct aspect ratios', function () {
    expect(ContentType::FacebookPost->aspectRatio())->toBeNull();
    expect(ContentType::FacebookReel->aspectRatio())->toBe('9:16');
    expect(ContentType::FacebookStory->aspectRatio())->toBe('9:16');
    expect(ContentType::YouTubeShort->aspectRatio())->toBe('9:16');
    expect(ContentType::TikTokVideo->aspectRatio())->toBe('9:16');
    expect(ContentType::TikTokPhoto->aspectRatio())->toBe('1:1');
});

test('content type has correct max media count', function () {
    expect(ContentType::FacebookPost->maxMediaCount())->toBe(10);
    expect(ContentType::FacebookReel->maxMediaCount())->toBe(1);
    expect(ContentType::FacebookStory->maxMediaCount())->toBe(1);
    expect(ContentType::TikTokVideo->maxMediaCount())->toBe(1);
    expect(ContentType::TikTokPhoto->maxMediaCount())->toBe(35);
    expect(ContentType::YouTubeShort->maxMediaCount())->toBe(1);
});

test('content type supports video correctly', function () {
    expect(ContentType::FacebookPost->supportsVideo())->toBeTrue();
    expect(ContentType::FacebookReel->supportsVideo())->toBeTrue();
    expect(ContentType::TikTokVideo->supportsVideo())->toBeTrue();
    expect(ContentType::YouTubeShort->supportsVideo())->toBeTrue();
    expect(ContentType::TikTokPhoto->supportsVideo())->toBeFalse();
});

test('content type supports image correctly', function () {
    expect(ContentType::FacebookPost->supportsImage())->toBeTrue();
    expect(ContentType::TikTokPhoto->supportsImage())->toBeTrue();
    expect(ContentType::FacebookReel->supportsImage())->toBeFalse();
    expect(ContentType::FacebookStory->supportsImage())->toBeFalse();
    expect(ContentType::TikTokVideo->supportsImage())->toBeFalse();
    expect(ContentType::YouTubeShort->supportsImage())->toBeFalse();
});

test('content type requires media correctly', function () {
    expect(ContentType::FacebookPost->requiresMedia())->toBeFalse();
    expect(ContentType::FacebookReel->requiresMedia())->toBeTrue();
    expect(ContentType::FacebookStory->requiresMedia())->toBeTrue();
    expect(ContentType::TikTokVideo->requiresMedia())->toBeTrue();
    expect(ContentType::TikTokPhoto->requiresMedia())->toBeTrue();
    expect(ContentType::YouTubeShort->requiresMedia())->toBeTrue();
});

test('no kept content type accepts gifs or documents', function (ContentType $type) {
    expect($type->acceptsGif())->toBeFalse()
        ->and($type->supportsDocument())->toBeFalse()
        ->and($type->maxDocumentBytes())->toBeNull()
        ->and($type->mediaRules()['accepts_gif'])->toBeFalse();
})->with(ContentType::cases());

/**
 * Lock the flags / limits that used to live in the Vue CONTENT_TYPE_RULES map
 * so a future centralization drift cannot silently flip editor behavior again.
 * Byte caps are the post-clamp values (min(platform, trypost.media hard limit)).
 */
test('media rules preserve pre-centralization editor limits for mapped types', function () {
    $mb = 1024 * 1024;
    $gb = 1024 * $mb;
    $hardImage = MediaType::Image->maxSizeInBytes();
    $hardVideo = MediaType::Video->maxSizeInBytes();

    $expected = [
        'youtube_short' => [
            'requires_media' => true,
            'accepts_gif' => false,
            'max_files' => 1,
            'max_video_duration_sec' => 180,
            // Platform advertises 256GB; editor must not exceed upload hard cap.
            'max_video_bytes' => $hardVideo,
        ],
        'facebook_post' => [
            'requires_media' => false,
            'accepts_gif' => false,
            'max_files' => 10,
            'max_video_duration_sec' => 240 * 60,
            'max_video_bytes' => $hardVideo,
        ],
        'tiktok_video' => [
            'requires_media' => true,
            'accepts_gif' => false,
            'max_files' => 1,
            'max_video_duration_sec' => 10 * 60,
            'max_video_bytes' => min(4 * $gb, $hardVideo),
        ],
        'tiktok_photo' => [
            'requires_media' => true,
            'accepts_gif' => false,
            'max_files' => 35,
            'max_image_bytes' => min(20 * $mb, $hardImage),
        ],
    ];

    foreach ($expected as $type => $fields) {
        $rules = ContentType::from($type)->mediaRules();

        foreach ($fields as $key => $value) {
            expect($rules[$key])->toBe($value, "{$type}.{$key}");
        }
    }
});

test('media byte caps never exceed the global upload hard limits', function () {
    $hardImage = MediaType::Image->maxSizeInBytes();
    $hardVideo = MediaType::Video->maxSizeInBytes();

    foreach (ContentType::cases() as $type) {
        $image = $type->maxImageBytes();
        $video = $type->maxVideoBytes();

        if ($image !== null) {
            expect($image)->toBeLessThanOrEqual($hardImage, "{$type->value}.max_image_bytes");
        }

        if ($video !== null) {
            expect($video)->toBeLessThanOrEqual($hardVideo, "{$type->value}.max_video_bytes");
        }
    }

    expect(ContentType::YouTubeShort->maxVideoBytes())->toBe($hardVideo)
        ->and(ContentType::FacebookPost->maxVideoBytes())->toBe($hardVideo);
});

test('can get content types for platform', function () {
    $facebookTypes = ContentType::forPlatform(Platform::Facebook);

    expect($facebookTypes)->toContain(ContentType::FacebookPost);
    expect($facebookTypes)->toContain(ContentType::FacebookReel);
    expect($facebookTypes)->toContain(ContentType::FacebookStory);
    expect($facebookTypes)->not->toContain(ContentType::TikTokVideo);

    expect(ContentType::forPlatform(Platform::TikTok))->toHaveCount(2);
    expect(ContentType::forPlatform(Platform::YouTube))->toHaveCount(1)->toContain(ContentType::YouTubeShort);
});

test('can get default content type for platform', function () {
    expect(ContentType::defaultFor(Platform::Facebook))->toBe(ContentType::FacebookPost);
    expect(ContentType::defaultFor(Platform::TikTok))->toBe(ContentType::TikTokVideo);
    expect(ContentType::defaultFor(Platform::YouTube))->toBe(ContentType::YouTubeShort);
});
