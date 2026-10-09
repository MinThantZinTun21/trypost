<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Enums\Insights\PageMetric;
use App\Enums\Insights\Range;
use App\Models\PageInsightSnapshot;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SummarizePageInsights
{
    /**
     * Page insights for the last $range complete days and the same number of
     * days before them, read from stored Insights snapshots only. The Insights
     * page and get_page_insights both report through here.
     *
     * The range ends at the newest stored snapshot, never past the last day
     * Facebook finished counting, so a newest day not read yet never shows as
     * an empty day against a full previous range.
     *
     * @return array{
     *     range: int,
     *     from: string,
     *     to: string,
     *     metrics: array<int, array{key: string, current: int|null, previous: int|null}>,
     *     days: array<int, array<string, int|string|null>>,
     * }
     */
    public static function execute(SocialAccount $account, Range $range): array
    {
        $to = self::lastStoredDay($account);
        $from = $to->subDays($range->value - 1);
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($range->value - 1);

        $snapshots = $account->pageInsightSnapshots()
            ->whereBetween('date', [$previousFrom->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $current = $snapshots->filter(fn (PageInsightSnapshot $snapshot): bool => $snapshot->date->gte($from));
        $previous = $snapshots->filter(fn (PageInsightSnapshot $snapshot): bool => $snapshot->date->lt($from));

        return [
            'range' => $range->value,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'metrics' => array_map(fn (PageMetric $metric): array => [
                'key' => $metric->value,
                'current' => self::total($current, $metric),
                'previous' => self::total($previous, $metric),
            ], PageMetric::cases()),
            'days' => self::days($current, $from, $to),
        ];
    }

    private static function lastStoredDay(SocialAccount $account): CarbonImmutable
    {
        $lastCompleteDay = PageInsightSnapshot::lastCompleteDay();
        $latest = $account->pageInsightSnapshots()
            ->where('date', '<=', $lastCompleteDay->toDateString())
            ->max('date');

        return $latest === null ? $lastCompleteDay : CarbonImmutable::parse((string) $latest)->startOfDay();
    }

    /**
     * Every day in the range, in order. A day Facebook reported nothing for
     * has null values, so a chart shows a gap rather than a false zero.
     *
     * @param  Collection<int, PageInsightSnapshot>  $snapshots
     * @return array<int, array<string, int|string|null>>
     */
    private static function days(Collection $snapshots, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byDate = $snapshots->keyBy(fn (PageInsightSnapshot $snapshot): string => $snapshot->date->toDateString());
        $days = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $snapshot = $byDate->get($day->toDateString());

            $days[] = [
                'date' => $day->toDateString(),
                ...collect(PageMetric::columns())->mapWithKeys(fn (string $column): array => [$column => $snapshot?->{$column}])->all(),
            ];
        }

        return $days;
    }

    /**
     * @param  Collection<int, PageInsightSnapshot>  $snapshots
     */
    private static function total(Collection $snapshots, PageMetric $metric): ?int
    {
        $values = $snapshots->pluck($metric->value)->filter(fn (?int $value): bool => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return $metric->isRunningTotal() ? $values->last() : $values->sum();
    }
}
