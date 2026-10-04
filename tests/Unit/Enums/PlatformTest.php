<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;

test('platform has correct labels', function () {
    expect(Platform::TikTok->label())->toBe('TikTok');
    expect(Platform::YouTube->label())->toBe('YouTube Shorts');
    expect(Platform::Facebook->label())->toBe('Facebook Page');
});

test('platform has correct colors', function () {
    expect(Platform::TikTok->color())->toBe('#000000');
    expect(Platform::YouTube->color())->toBe('#FF0000');
    expect(Platform::Facebook->color())->toBe('#1877F2');
});

test('platform has correct allowed media types', function () {
    expect(Platform::TikTok->allowedMediaTypes())->toBe([MediaType::Video]);
    expect(Platform::YouTube->allowedMediaTypes())->toBe([MediaType::Video]);
    expect(Platform::Facebook->allowedMediaTypes())->toBe([MediaType::Image, MediaType::Video]);
});

test('platform has correct max images', function () {
    expect(Platform::TikTok->maxImages())->toBe(0);
    expect(Platform::YouTube->maxImages())->toBe(0);
    expect(Platform::Facebook->maxImages())->toBe(10);
});

test('platform has correct max content length', function () {
    expect(Platform::TikTok->maxContentLength())->toBe(2200);
    expect(Platform::YouTube->maxContentLength())->toBe(100);
    expect(Platform::Facebook->maxContentLength())->toBe(10000);
});

test('platform supports text only correctly', function () {
    expect(Platform::Facebook->supportsTextOnly())->toBeTrue();

    expect(Platform::TikTok->supportsTextOnly())->toBeFalse();
    expect(Platform::YouTube->supportsTextOnly())->toBeFalse();
});

test('platform is enabled by default for every platform', function (Platform $platform) {
    expect($platform->isEnabled())->toBeTrue();
})->with([
    Platform::TikTok,
    Platform::YouTube,
    Platform::Facebook,
]);

test('each platform can be disabled via config', function (Platform $platform) {
    config(["trypost.platforms.{$platform->value}.enabled" => false]);

    expect($platform->isEnabled())->toBeFalse();
    expect($platform->isConnectable())->toBeFalse();
})->with([
    Platform::TikTok,
    Platform::YouTube,
    Platform::Facebook,
]);

test('each platform maps to its publishing queue', function (Platform $platform, string $queue) {
    expect($platform->queue())->toBe($queue);
})->with([
    [Platform::TikTok, 'social-tiktok'],
    [Platform::YouTube, 'social-youtube'],
    [Platform::Facebook, 'social-facebook'],
]);

test('allQueues lists every platform publishing queue in enum order', function () {
    expect(Platform::allQueues())->toBe([
        'social-facebook',
        'social-tiktok',
        'social-youtube',
    ])->and(Platform::allQueues())->toHaveCount(count(Platform::cases()));
});

test('enabledQueues matches allQueues when every platform is enabled', function () {
    foreach (Platform::cases() as $platform) {
        config(["trypost.platforms.{$platform->value}.enabled" => true]);
    }

    expect(Platform::enabledQueues())->toBe(Platform::allQueues());
});

test('disabling a platform removes only its queue from enabledQueues', function (Platform $disabled) {
    foreach (Platform::cases() as $platform) {
        config(["trypost.platforms.{$platform->value}.enabled" => true]);
    }

    config(["trypost.platforms.{$disabled->value}.enabled" => false]);

    $enabledQueues = Platform::enabledQueues();

    expect($enabledQueues)
        ->not->toContain($disabled->queue())
        ->toHaveCount(count(Platform::cases()) - 1);

    foreach (Platform::cases() as $platform) {
        if ($platform === $disabled) {
            continue;
        }

        expect($enabledQueues)->toContain($platform->queue());
    }

    expect(Platform::allQueues())->toContain($disabled->queue());
})->with([
    Platform::TikTok,
    Platform::YouTube,
    Platform::Facebook,
]);

test('disabling every platform yields no enabled queues', function () {
    foreach (Platform::cases() as $platform) {
        config(["trypost.platforms.{$platform->value}.enabled" => false]);
    }

    expect(Platform::enabledQueues())->toBe([]);
});

test('horizon social publishing queues match enabledQueues', function () {
    expect(config('horizon.defaults.social-publishing.queue'))->toBe(Platform::enabledQueues());
});

test('isEnabled falls back to env when enabled config is missing', function (Platform $platform, string $envKey) {
    $platforms = config('trypost.platforms');
    unset($platforms[$platform->value]['enabled']);
    config(['trypost.platforms' => $platforms]);

    $original = getenv($envKey);
    putenv("{$envKey}=false");

    try {
        expect($platform->isEnabled())->toBeFalse();
    } finally {
        if ($original === false) {
            putenv($envKey);
        } else {
            putenv("{$envKey}={$original}");
        }
    }
})->with([
    [Platform::TikTok, 'TIKTOK_ENABLED'],
    [Platform::YouTube, 'YOUTUBE_ENABLED'],
    [Platform::Facebook, 'FACEBOOK_ENABLED'],
]);

test('connectable options are sorted alphabetically by label', function () {
    $labels = array_column(Platform::connectableOptions(), 'label');

    $sorted = $labels;
    natcasesort($sorted);

    expect($labels)->toBe(array_values($sorted));
});

test('connectable options list every enabled platform', function () {
    expect(array_column(Platform::connectableOptions(), 'value'))
        ->toEqualCanonicalizing(['facebook', 'tiktok', 'youtube']);
});

test('tiktok publish config privacy options come from the privacy level enum', function () {
    expect(Platform::TikTok->publishConfig()['privacyLevelOptions'])->toBe(PrivacyLevel::values());
});
