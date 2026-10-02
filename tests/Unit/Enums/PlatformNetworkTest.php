<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;

test('network groups the two linkedin variants together', function () {
    expect(Platform::LinkedIn->network())->toBe('linkedin')
        ->and(Platform::LinkedInPage->network())->toBe('linkedin');
});

test('network groups the two instagram variants together', function () {
    expect(Platform::Instagram->network())->toBe('instagram')
        ->and(Platform::InstagramFacebook->network())->toBe('instagram');
});

test('network returns the platform value for single-variant networks', function () {
    expect(Platform::X->network())->toBe('x')
        ->and(Platform::Facebook->network())->toBe('facebook')
        ->and(Platform::TikTok->network())->toBe('tiktok')
        ->and(Platform::YouTube->network())->toBe('youtube')
        ->and(Platform::Bluesky->network())->toBe('bluesky');
});

test('every platform resolves to a non-empty network', function () {
    foreach (Platform::cases() as $platform) {
        expect($platform->network())->toBeString()->not->toBe('');
    }
});
