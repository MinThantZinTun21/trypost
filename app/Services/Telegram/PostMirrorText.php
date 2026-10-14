<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;

/**
 * The text of a Post's Telegram mirror: its content (or, when that is
 * empty, its YouTube title) followed by a link to each live Post platform.
 */
class PostMirrorText
{
    public static function for(Post $post): string
    {
        $published = $post->postPlatforms
            ->where('enabled', true)
            ->where('status', PostPlatformStatus::Published)
            ->filter(fn (PostPlatform $target): bool => filled($target->platform_url));

        $links = $published
            ->map(fn (PostPlatform $target): string => "{$target->notificationLabel()}: {$target->platform_url}")
            ->implode("\n");

        return collect([
            self::body($post),
            $links === '' ? null : __('telegram.live_on')."\n{$links}",
        ])->filter(fn (?string $part): bool => filled($part))->implode("\n\n");
    }

    private static function body(Post $post): ?string
    {
        if (filled($post->content)) {
            return trim($post->content);
        }

        $youtube = $post->postPlatforms->first(
            fn (PostPlatform $target): bool => $target->platform === Platform::YouTube && filled(data_get($target->meta, 'title')),
        );

        return $youtube ? data_get($youtube->meta, 'title') : null;
    }
}
