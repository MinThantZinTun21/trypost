<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Rules\ContentFitsPlatformLimits;

function runFitsRule(string $content, array $platforms): array
{
    $errors = [];
    $rule = new ContentFitsPlatformLimits(collect($platforms));

    $rule->validate('content', $content, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

test('passes when content fits every platform cap', function () {
    $errors = runFitsRule(str_repeat('a', 100), [Platform::YouTube, Platform::TikTok, Platform::Facebook]);

    expect($errors)->toBe([]);
});

test('fails with the platform label, limit and overage when content exceeds a single platform', function () {
    $errors = runFitsRule(str_repeat('a', 137), [Platform::YouTube]);

    expect($errors)->toHaveCount(1);
    expect($errors[0])
        ->toContain('YouTube')
        ->toContain('100')
        ->toContain('37');
});

test('emits one error per overflowing platform in a multi-platform set', function () {
    // 2300 chars: fine for Facebook (10000), over for YouTube (100) and TikTok (2200).
    $errors = runFitsRule(str_repeat('a', 2300), [Platform::YouTube, Platform::TikTok, Platform::Facebook]);

    expect($errors)->toHaveCount(2);
    expect($errors[0])->toContain('YouTube');
    expect($errors[1])->toContain('TikTok');
});

test('deduplicates errors when the same platform appears twice in the collection', function () {
    // Two TikTok accounts selected, content 2300 chars — should still produce ONE error.
    $errors = runFitsRule(str_repeat('a', 2300), [Platform::TikTok, Platform::TikTok]);

    expect($errors)->toHaveCount(1);
});

test('passes for an empty platforms collection', function () {
    $errors = runFitsRule(str_repeat('a', 10_000), []);

    expect($errors)->toBe([]);
});

test('treats null content as an empty string and passes', function () {
    $errors = runFitsRule('', [Platform::YouTube, Platform::TikTok]);

    expect($errors)->toBe([]);
});

test('does not count html markup toward a platform cap', function () {
    $errors = runFitsRule('<p>'.str_repeat('a', 95).'</p>', [Platform::YouTube]);

    expect($errors)->toBe([]);
});
