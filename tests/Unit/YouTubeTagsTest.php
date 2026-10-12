<?php

declare(strict_types=1);

use App\Support\YouTubeTags;

test('tags are trimmed, lose a leading hash and drop blanks and duplicates', function () {
    expect(YouTubeTags::normalize(['  cooking ', '#recipes', '', '   ', 'Cooking', 'quick  dinner']))
        ->toBe(['cooking', 'recipes', 'quick dinner']);
});

test('anything other than a list of text normalizes to no tags', function (mixed $tags) {
    expect(YouTubeTags::normalize($tags))->toBe([]);
})->with([
    'null' => [null],
    'text' => ['cooking, recipes'],
    'number' => [42],
]);

test('the length counts commas between tags and quotes around tags with a space', function () {
    expect(YouTubeTags::length(['Foo-Baz']))->toBe(7)
        ->and(YouTubeTags::length(['Foo Baz']))->toBe(9)
        ->and(YouTubeTags::length(['Foo-Baz', 'Foo Baz']))->toBe(17)
        ->and(YouTubeTags::length([]))->toBe(0);
});

test('tag limits follow the youtube data api', function (mixed $tags, ?string $key) {
    expect(YouTubeTags::violation($tags))->toBe($key);
})->with([
    'missing' => [null, null],
    'empty' => [[], null],
    'exactly five hundred characters' => [[str_repeat('a', 249), str_repeat('b', 250)], null],
    'over five hundred characters' => [[str_repeat('a', 250), str_repeat('b', 250)], 'posts.form.youtube.tags_max'],
    'quotes push a spaced tag over' => [[str_repeat('a', 249).' '.str_repeat('b', 249)], 'posts.form.youtube.tags_max'],
    'not a list' => ['cooking', 'posts.form.youtube.tags_invalid'],
    'a tag that is not text' => [['cooking', ['nested']], 'posts.form.youtube.tags_invalid'],
]);

test('resolve reads the normalized tags from post platform meta', function () {
    expect(YouTubeTags::resolve(['tags' => [' #shorts ', 'shorts', 'food']]))->toBe(['shorts', 'food'])
        ->and(YouTubeTags::resolve(null))->toBe([])
        ->and(YouTubeTags::resolve(['title' => 'No tags']))->toBe([]);
});
