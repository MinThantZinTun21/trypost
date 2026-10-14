<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Dto\MediaItem;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Support\Facades\URL;

/**
 * The text of a Post's Telegram mirror: its content (or, when that is
 * empty, its YouTube title), a Download link per video, then a link to each
 * live Post platform.
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
        $liveOn = __('telegram.live_on');

        return collect([
            self::body($post),
            self::downloads($post),
            $links === '' ? null : "{$liveOn}\n{$links}",
        ])->filter(fn (?string $part): bool => filled($part))->implode("\n\n");
    }

    /**
     * A permanent signed Download link (DownloadMediaController) for each
     * video whose Media still exists.
     */
    private static function downloads(Post $post): string
    {
        $videos = $post->media_items->filter(fn (MediaItem $item): bool => $item->isVideo());

        if ($videos->isEmpty()) {
            return '';
        }

        $existing = Media::query()->whereKey($videos->pluck('id')->all())->pluck('id');

        return $videos
            ->filter(fn (MediaItem $item): bool => $existing->contains($item->id))
            ->map(function (MediaItem $item): string {
                $label = __('telegram.download', ['name' => $item->filename()]);
                $url = URL::signedRoute('media.download', ['media' => $item->id]);

                return "{$label}: {$url}";
            })
            ->implode("\n");
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
