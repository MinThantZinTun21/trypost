<?php

declare(strict_types=1);

namespace App\Support\Social;

/**
 * Shared keys and readers for in-flight publish checkpoints on
 * PostPlatform.error_context. Used by the publishers, the TikTok
 * derivative cleaner, and posts:retry.
 */
final class PublishCheckpoint
{
    public const string TIKTOK_PUBLISH_ID = 'tiktok_publish_id';

    public const string TIKTOK_STATUS = 'tiktok_status';

    public const string TIKTOK_DERIVATIVE_PATHS = 'tiktok_derivative_paths';

    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function tiktokPublishId(?array $context): ?string
    {
        $value = data_get($context, self::TIKTOK_PUBLISH_ID);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<array-key, mixed>
     */
    public static function tiktokDerivativePaths(?array $context): array
    {
        $paths = data_get($context, self::TIKTOK_DERIVATIVE_PATHS, []);

        return is_array($paths) ? $paths : [];
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public static function tiktokStatus(?array $context): ?string
    {
        $value = data_get($context, self::TIKTOK_STATUS);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
