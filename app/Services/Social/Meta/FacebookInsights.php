<?php

declare(strict_types=1);

namespace App\Services\Social\Meta;

use App\Enums\Insights\PageMetric;
use App\Exceptions\Social\InsightsReadException;
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
     * so the day a value belongs to is the day before its end_time.
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

                if (is_numeric($value)) {
                    $days[$date][$metric->value] = (int) $value;
                }
            }
        }

        ksort($days);

        return $days;
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
