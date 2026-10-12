<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A YouTube Short's Tags. YouTube allows 500 characters for the whole list,
 * counting the commas between tags and the quotes it puts around a tag with
 * a space (Data API v3, videos#snippet.tags[]).
 */
class YouTubeTags
{
    public const int MAX_CHARACTERS = 500;

    public static function violation(mixed $tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        if (! is_array($tags) || collect($tags)->contains(fn (mixed $tag): bool => ! is_string($tag) || ! mb_check_encoding($tag, 'UTF-8'))) {
            return 'posts.form.youtube.tags_invalid';
        }

        return self::length(self::normalize($tags)) > self::MAX_CHARACTERS ? 'posts.form.youtube.tags_max' : null;
    }

    /**
     * The Tags sent to YouTube, read from the Post platform's meta.
     *
     * @param  array<string, mixed>|null  $meta
     * @return list<string>
     */
    public static function resolve(?array $meta): array
    {
        return self::normalize(data_get($meta, 'tags'));
    }

    /**
     * Trimmed tags without a leading `#`, blanks or repeats (compared without case).
     *
     * @return list<string>
     */
    public static function normalize(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->filter(fn (mixed $tag): bool => is_string($tag))
            ->map(fn (string $tag): string => Str::squish(ltrim(Str::squish($tag), '#')))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values()
            ->all();
    }

    /**
     * The length YouTube counts for a list of tags.
     *
     * @param  list<string>  $tags
     */
    public static function length(array $tags): int
    {
        if ($tags === []) {
            return 0;
        }

        $characters = collect($tags)->sum(fn (string $tag): int => mb_strlen($tag) + (str_contains($tag, ' ') ? 2 : 0));

        return $characters + count($tags) - 1;
    }
}
