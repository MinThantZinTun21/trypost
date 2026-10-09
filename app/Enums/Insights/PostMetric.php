<?php

declare(strict_types=1);

namespace App\Enums\Insights;

/**
 * One Post insight the app stores as a lifetime total. The value is the Post
 * insights column. Feed metrics are read from the feed post's insights; video
 * metrics from the video's insights, for video Posts and Reels only (ADR 0004).
 */
enum PostMetric: string
{
    case Views = 'views';
    case Reach = 'reach';
    case Reactions = 'reactions';
    case Clicks = 'clicks';
    case VideoViews = 'video_views';
    case AvgWatchTimeMs = 'avg_watch_time_ms';
    case WatchTimeMs = 'watch_time_ms';
    case ReelPlays = 'reel_plays';

    public function graphMetric(): string
    {
        return match ($this) {
            self::Views => 'post_media_view',
            self::Reach => 'post_total_media_view_unique',
            self::Reactions => 'post_reactions_by_type_total',
            self::Clicks => 'post_clicks',
            self::VideoViews => 'post_video_views',
            self::AvgWatchTimeMs => 'post_video_avg_time_watched',
            self::WatchTimeMs => 'post_video_view_time',
            self::ReelPlays => 'blue_reels_play_count',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function feedMetrics(bool $isVideo): array
    {
        return $isVideo
            ? [self::Views, self::Reach, self::Reactions, self::Clicks, self::VideoViews]
            : [self::Views, self::Reach, self::Reactions, self::Clicks];
    }

    /**
     * @return array<int, self>
     */
    public static function videoMetrics(): array
    {
        return [self::AvgWatchTimeMs, self::WatchTimeMs, self::ReelPlays];
    }

    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return array_map(fn (self $metric): string => $metric->value, self::cases());
    }
}
