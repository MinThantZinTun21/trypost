<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;

test('altTextMaxLength returns the documented cap for supporting platforms', function () {
    expect(Platform::Facebook->altTextMaxLength())->toBe(1000);
});

test('altTextMaxLength is null for platforms without alt-text support', function () {
    expect(Platform::TikTok->altTextMaxLength())->toBeNull()
        ->and(Platform::YouTube->altTextMaxLength())->toBeNull();
});

test('supportsAltText mirrors altTextMaxLength', function () {
    expect(Platform::Facebook->supportsAltText())->toBeTrue()
        ->and(Platform::TikTok->supportsAltText())->toBeFalse()
        ->and(Platform::YouTube->supportsAltText())->toBeFalse();
});

test('altTextMaxLength is defined for every platform so a new case cannot slip through', function () {
    foreach (Platform::cases() as $platform) {
        $max = $platform->altTextMaxLength();

        expect($max === null || (is_int($max) && $max > 0))->toBeTrue();
    }
});
