<?php

declare(strict_types=1);

namespace App\Enums\Insights;

/**
 * One Page insight the app stores per day. The value is the Insights snapshot
 * column; graphMetric() is the Graph v25 metric it is read from (ADR 0004).
 */
enum PageMetric: string
{
    case Followers = 'followers';
    case NewFollows = 'new_follows';
    case Unfollows = 'unfollows';
    case Views = 'views';
    case Reach = 'reach';
    case Engagements = 'engagements';
    case VideoViews = 'video_views';

    public function graphMetric(): string
    {
        return match ($this) {
            self::Followers => 'page_follows',
            self::NewFollows => 'page_daily_follows_unique',
            self::Unfollows => 'page_daily_unfollows_unique',
            self::Views => 'page_media_view',
            self::Reach => 'page_total_media_view_unique',
            self::Engagements => 'page_post_engagements',
            self::VideoViews => 'page_video_views',
        };
    }

    /**
     * Followers is a running total, so a range reports its latest value;
     * every other metric is a daily count, so a range reports the sum.
     */
    public function isRunningTotal(): bool
    {
        return $this === self::Followers;
    }

    public static function fromGraphMetric(string $graphMetric): ?self
    {
        foreach (self::cases() as $metric) {
            if ($metric->graphMetric() === $graphMetric) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return array_map(fn (self $metric): string => $metric->value, self::cases());
    }
}
