<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;

test('only facebook, tiktok and youtube are supported platforms', function () {
    expect(array_map(fn (Platform $platform): string => $platform->value, Platform::cases()))
        ->toBe(['facebook', 'tiktok', 'youtube']);
});

test('only the six kept content types exist', function () {
    expect(array_map(fn (ContentType $contentType): string => $contentType->value, ContentType::cases()))
        ->toEqualCanonicalizing([
            'facebook_post',
            'facebook_reel',
            'facebook_story',
            'tiktok_video',
            'tiktok_photo',
            'youtube_short',
        ]);
});

test('every content type belongs to a supported platform', function () {
    foreach (ContentType::cases() as $contentType) {
        expect(Platform::cases())->toContain($contentType->platform());
    }
});
