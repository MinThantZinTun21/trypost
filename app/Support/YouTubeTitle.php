<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A YouTube Short's Title: the Owner's own when set, otherwise the first
 * sentence of the content. YouTube allows 100 characters and rejects `<` and
 * `>` (Data API v3, videos#snippet.title).
 */
class YouTubeTitle
{
    public const int MAX_CHARACTERS = 100;

    private const string SHORTS_TAG = ' #Shorts';

    public static function violation(mixed $title): ?string
    {
        if ($title === null) {
            return null;
        }

        return match (true) {
            ! is_string($title),
            ! mb_check_encoding($title, 'UTF-8') => 'posts.form.youtube.title_invalid',
            mb_strlen($title) > self::MAX_CHARACTERS => 'posts.form.youtube.title_max',
            Str::contains($title, ['<', '>']) => 'posts.form.youtube.title_angle_brackets',
            default => null,
        };
    }

    /**
     * The Title sent to YouTube, or null when neither a Title nor content exists.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function resolve(?array $meta, ?string $content): ?string
    {
        return self::custom($meta) ?? (filled($content) ? self::fromContent($content) : null);
    }

    /**
     * The Owner's own Title, trimmed, or null when it is not set.
     */
    public static function custom(mixed $meta): ?string
    {
        return PostPlatformText::trimmed($meta, 'title');
    }

    /**
     * The first sentence of the first line, cut to leave room for the
     * `#Shorts` tag, without the characters YouTube rejects.
     */
    public static function fromContent(string $content): string
    {
        $availableLength = self::MAX_CHARACTERS - mb_strlen(self::SHORTS_TAG);

        $firstLine = explode("\n", $content)[0];
        $title = str_replace(['<', '>'], '', explode('.', $firstLine)[0]);

        if (mb_strlen($title) > $availableLength) {
            $title = mb_substr($title, 0, $availableLength - 3).'...';
        }

        return $title.self::SHORTS_TAG;
    }
}
