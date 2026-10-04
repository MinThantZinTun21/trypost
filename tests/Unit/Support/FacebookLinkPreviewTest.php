<?php

declare(strict_types=1);

use App\Support\FacebookLinkPreview;

/**
 * Captions the publisher resolves to a `link` attachment.
 * A null value means the post goes out as plain text.
 *
 * @return array<string, ?string>
 */
function facebookLinkPreviewCorpus(): array
{
    return [
        'no link here' => null,
        'Read https://example.com/post today' => 'https://example.com/post',
        'See https://example.com/post.' => 'https://example.com/post',
        'See https://example.com/post).' => 'https://example.com/post',
        'See https://www.facebook.com/page' => null,
        'See https://m.facebook.com/page' => null,
        'See https://facebook.com/events/1' => null,
        'See https://FB.com/page' => null,
        'See https://fb.me/abc' => null,
        'See https://l.facebook.com/l.php?u=https://example.com' => null,
        'See https://www.facebook.com/page and https://example.com/post.' => 'https://example.com/post',
        'See https://notfacebook.com/post' => 'https://notfacebook.com/post',
        'See https://example.com/a..' => 'https://example.com/a.',
    ];
}

test('facebook link preview picks the url the page feed will accept', function () {
    foreach (facebookLinkPreviewCorpus() as $text => $expected) {
        expect(FacebookLinkPreview::url($text))->toBe($expected);
    }
});
