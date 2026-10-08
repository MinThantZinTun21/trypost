<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reads the per-account text (Title, Description, caption) a Post platform's
 * meta carries, the way every publisher and rule treats it.
 */
class PostPlatformText
{
    /**
     * The trimmed text under `$key`, or null when it is missing or blank.
     */
    public static function trimmed(mixed $meta, string $key): ?string
    {
        $value = data_get($meta, $key);

        return is_string($value) && filled(Str::trim($value)) ? Str::trim($value) : null;
    }
}
