<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Services\Social\ContentSanitizer;

test('it strips html tags for plain text platforms', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Hello <strong>world</strong></p>', Platform::Facebook);
    expect($result)->toBe('Hello world');
});

test('it converts p tags to newlines', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>First paragraph</p><p>Second paragraph</p>', Platform::TikTok);
    expect($result)->toBe("First paragraph\nSecond paragraph");
});

test('it converts br to newlines', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Line one<br>Line two', Platform::Facebook);
    expect($result)->toBe("Line one\nLine two");
});

test('it decodes html entities', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Tom &amp; Jerry &lt;3', Platform::YouTube);
    expect($result)->toBe('Tom & Jerry <3');
});

test('it returns plain text unchanged', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Just plain text', Platform::TikTok);
    expect($result)->toBe('Just plain text');
});

test('it returns an empty string for empty content on every platform', function (Platform $platform) {
    $sanitizer = new ContentSanitizer;

    expect($sanitizer->sanitize('', $platform))->toBe('');
})->with(Platform::cases());

test('it converts list items to dashes', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<ul><li>Item one</li><li>Item two</li></ul>', Platform::Facebook);
    expect($result)->toContain('- Item one');
    expect($result)->toContain('- Item two');
});

test('it keeps links intact on every platform', function (string $input) {
    $sanitizer = new ContentSanitizer;

    foreach (Platform::cases() as $platform) {
        expect($sanitizer->sanitize("See {$input}", $platform))->toContain($input);
    }
})->with([
    'bare' => ['acme.com'],
    'two-level TLD' => ['acme.com.br'],
    'sub' => ['blog.acme.com'],
    'full url' => ['https://org.blog.acme.com.br/a?b=c'],
]);

test('it measures a platform limit against the rendered text, not the markup', function () {
    $sanitizer = new ContentSanitizer;
    $content = '<p>'.str_repeat('a', 100).'</p>';

    expect($sanitizer->displayText($content, Platform::YouTube))->toBe(str_repeat('a', 100))
        ->and(Platform::YouTube->contentOverflow($sanitizer->displayText($content, Platform::YouTube)))
        ->toBe(0);
});

test('display text is the sanitized content on every platform', function (Platform $platform) {
    $sanitizer = new ContentSanitizer;
    $content = '<p>Tom &amp;amp; <strong>Jerry</strong></p>';

    expect($sanitizer->displayText($content, $platform))
        ->toBe($sanitizer->sanitize($content, $platform));
})->with(Platform::cases());
