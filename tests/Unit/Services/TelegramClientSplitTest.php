<?php

declare(strict_types=1);

use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Str;

test('length counts UTF-16 code units like Telegram', function () {
    expect(TelegramClient::length('abc'))->toBe(3)
        ->and(TelegramClient::length('é'))->toBe(1)
        ->and(TelegramClient::length('😀'))->toBe(2);
});

test('text within the limit stays one chunk', function () {
    expect(TelegramClient::split("Hello\n\nworld", 20))->toBe(["Hello\n\nworld"]);
});

test('text is split between lines so links stay whole', function () {
    $link = 'https://example.test/media/abc/download?signature=0123456789';
    $text = Str::repeat('a', 50)."\n{$link}";

    $chunks = TelegramClient::split($text, 70);

    expect($chunks)->toBe([Str::repeat('a', 50), $link]);
});

test('a line longer than the limit is cut and nothing is lost', function () {
    $text = Str::repeat('😀', 30);

    $chunks = TelegramClient::split($text, 20);

    expect(collect($chunks)->every(fn (string $chunk): bool => TelegramClient::length($chunk) <= 20))->toBeTrue()
        ->and(implode('', $chunks))->toBe($text);
});
