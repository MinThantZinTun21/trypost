<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class YouTubeDescription
{
    public const int MAX_BYTES = 5000;

    public static function violation(mixed $description): ?string
    {
        if ($description === null) {
            return null;
        }

        return match (true) {
            ! is_string($description),
            ! mb_check_encoding($description, 'UTF-8') => 'posts.form.youtube.description_invalid',
            strlen($description) > self::MAX_BYTES => 'posts.form.youtube.description_max',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function resolve(?array $meta, ?string $content): string
    {
        return self::custom($meta) ?? ($content ?? '');
    }

    /**
     * The Description set for the Short, as written (YouTube keeps its
     * whitespace), or null when the content stands in for it.
     */
    public static function custom(mixed $meta): ?string
    {
        $description = data_get($meta, 'description');

        return is_string($description) && filled(Str::trim($description)) ? $description : null;
    }

    /**
     * The limit the content breaks when it stands in for the Description. Only
     * a Title lifts the Platform's content cap (PostPlatformMetaRules::contentLimitApplies),
     * so without one the content is already short enough.
     */
    public static function contentViolation(mixed $meta, ?string $content): ?string
    {
        if (YouTubeTitle::custom($meta) === null || self::custom($meta) !== null) {
            return null;
        }

        return self::violation($content ?? '') === null ? null : 'posts.form.youtube.description_from_content_max';
    }
}
