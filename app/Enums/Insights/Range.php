<?php

declare(strict_types=1);

namespace App\Enums\Insights;

/**
 * How many complete days the Insights page and get_page_insights cover.
 */
enum Range: int
{
    case Week = 7;
    case FourWeeks = 28;
    case Quarter = 90;

    public const DEFAULT = self::FourWeeks;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(fn (self $range): int => $range->value, self::cases());
    }
}
