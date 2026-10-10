<?php

declare(strict_types=1);

namespace App\Services\Social\Meta;

use App\Enums\Insights\PageMetric;
use App\Enums\Insights\PostMetric;
use App\Exceptions\Social\InsightsReadException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Reads Insights for a Facebook Page from the Graph API (ADR 0004). Only the
 * metrics Graph v25 still answers are requested: one invalid metric fails the
 * whole request, so the set lives in PageMetric and nowhere else.
 */
class FacebookInsights
{
    private const TIMEOUT_SECONDS = 30;

    /**
     * Daily Page insights from $since to $until (both inclusive), keyed by date.
     *
     * Graph stamps each daily value with the end of that day (Pacific time),
     * so the day a value belongs to is the day before its end_time. Days
     * Graph returns outside the window asked for are dropped.
     *
     * @return array<string, array<string, int>>
     */
    public function pageDays(SocialAccount $account, CarbonInterface $since, CarbonInterface $until): array
    {
        $response = $this->get($account, "{$this->pageId($account)}/insights", [
            'metric' => collect(PageMetric::cases())->map(fn (PageMetric $metric): string => $metric->graphMetric())->implode(','),
            'period' => 'day',
            'since' => $since->toDateString(),
            'until' => $until->addDay()->toDateString(),
        ]);

        $days = [];

        foreach ($response->json('data') ?? [] as $series) {
            $metric = PageMetric::fromGraphMetric((string) data_get($series, 'name'));

            if ($metric === null) {
                continue;
            }

            foreach (data_get($series, 'values', []) as $point) {
                $date = CarbonImmutable::parse((string) data_get($point, 'end_time'))->subDay()->toDateString();
                $value = data_get($point, 'value');

                if ($date < $since->toDateString() || $date > $until->toDateString()) {
                    continue;
                }

                if (is_numeric($value)) {
                    $days[$date][$metric->value] = (int) $value;
                }
            }
        }

        ksort($days);

        return $days;
    }

    /**
     * The feed post id a Post platform's Post insights live under. A text or
     * photo Post's stored id is already one; a video Post or Reel stores the
     * video id, so its feed post is looked up (once: the caller stores it).
     */
    public function feedPostId(SocialAccount $account, PostPlatform $postPlatform): string
    {
        $platformPostId = (string) $postPlatform->platform_post_id;

        if (! $postPlatform->publishesVideo()) {
            return $platformPostId;
        }

        $postId = $this->get($account, $platformPostId, ['fields' => 'post_id'])->json('post_id');

        if (! is_string($postId) || $postId === '') {
            throw new InsightsReadException(__('insights.no_feed_post', ['id' => $platformPostId]));
        }

        return str_contains($postId, '_') ? $postId : "{$this->pageId($account)}_{$postId}";
    }

    /**
     * Lifetime Post insights for one Post platform, read under its feed post
     * id. For a video the video's own insights are read too.
     *
     * @return array<string, int|null> each PostMetric column read
     */
    public function postTotals(SocialAccount $account, PostPlatform $postPlatform, string $feedPostId): array
    {
        $isVideo = $postPlatform->publishesVideo();
        $totals = $this->lifetimeValues($account, "{$feedPostId}/insights", PostMetric::feedMetrics($isVideo));

        if ($isVideo) {
            $totals = [
                ...$totals,
                ...$this->lifetimeValues($account, "{$postPlatform->platform_post_id}/video_insights", PostMetric::videoMetrics()),
            ];
        }

        return $totals;
    }

    /**
     * A lifetime metric's value is a number, or a count per reaction type,
     * which is summed.
     *
     * @param  array<int, PostMetric>  $metrics
     * @return array<string, int|null>
     */
    private function lifetimeValues(SocialAccount $account, string $path, array $metrics): array
    {
        $response = $this->get($account, $path, [
            'metric' => implode(',', array_map(fn (PostMetric $metric): string => $metric->graphMetric(), $metrics)),
        ]);

        $values = array_fill_keys(array_map(fn (PostMetric $metric): string => $metric->value, $metrics), null);

        foreach ($response->json('data') ?? [] as $series) {
            $metric = PostMetric::fromGraphMetric((string) data_get($series, 'name'));
            $value = data_get($series, 'values.0.value');

            if ($metric === null || ! in_array($metric, $metrics, true)) {
                continue;
            }

            if (is_array($value)) {
                $values[$metric->value] = (int) array_sum($value);
            } elseif (is_numeric($value)) {
                $values[$metric->value] = (int) round((float) $value);
            }
        }

        return $values;
    }

    private function pageId(SocialAccount $account): string
    {
        return (string) $account->platform_user_id;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(SocialAccount $account, string $path, array $query): Response
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(config('trypost.platforms.facebook.graph_api')."/{$path}", [
                    ...$query,
                    'access_token' => $account->access_token,
                ]);
        } catch (ConnectionException $e) {
            throw InsightsReadException::unreachable($e->getMessage());
        }

        if (! $response->successful()) {
            throw InsightsReadException::fromResponse($response);
        }

        return $response;
    }
}
