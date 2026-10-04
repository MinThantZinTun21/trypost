<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;

test('network returns the platform value', function () {
    expect(Platform::Facebook->network())->toBe('facebook')
        ->and(Platform::TikTok->network())->toBe('tiktok')
        ->and(Platform::YouTube->network())->toBe('youtube');
});

test('every platform resolves to a non-empty network', function () {
    foreach (Platform::cases() as $platform) {
        expect($platform->network())->toBeString()->not->toBe('');
    }
});
