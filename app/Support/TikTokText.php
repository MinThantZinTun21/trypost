<?php

declare(strict_types=1);

namespace App\Support;

/**
 * TikTok's per-Post text: the caption of a Video, and the Title and
 * Description of a Photo. TikTok counts every limit in UTF-16 runes (Content
 * Posting API, post_info), so an emoji outside the BMP counts twice.
 */
class TikTokText
{
    public const int CAPTION_MAX = 2200;

    public const int PHOTO_TITLE_MAX = 90;

    public const int PHOTO_DESCRIPTION_MAX = 4000;

    public static function length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * The translation key of the limit the text breaks, or null when it fits.
     */
    public static function violation(mixed $text, int $max, string $maxKey): ?string
    {
        if ($text === null) {
            return null;
        }

        return match (true) {
            ! is_string($text),
            ! mb_check_encoding($text, 'UTF-8') => 'posts.form.tiktok.text_invalid',
            self::length($text) > $max => $maxKey,
            default => null,
        };
    }
}
