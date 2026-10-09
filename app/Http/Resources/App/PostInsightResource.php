<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Enums\Insights\PostMetric;
use App\Models\PostInsight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PostInsight
 */
class PostInsightResource extends JsonResource
{
    /**
     * A Post platform's Post insights, or null before the first read (a row
     * may already hold the feed post id without any totals).
     *
     * @return array<string, mixed>|null
     */
    public static function resolveOrNull(?PostInsight $insight): ?array
    {
        return $insight?->read_at === null ? null : (new self($insight))->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...collect(PostMetric::columns())->mapWithKeys(fn (string $column): array => [$column => $this->{$column}])->all(),
            'read_at' => $this->read_at?->toIso8601String(),
        ];
    }
}
