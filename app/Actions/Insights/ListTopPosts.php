<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Http\Resources\App\PostInsightResource;
use App\Models\PostInsight;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ListTopPosts
{
    public const LIMIT = 5;

    /**
     * The Posts this app published to the Facebook Page between two dates
     * (inclusive), ranked by their Post insights views.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function execute(SocialAccount $account, string $from, string $to): array
    {
        return PostInsight::query()
            ->with('postPlatform.post')
            ->whereNotNull('views')
            ->whereHas('postPlatform', fn ($query) => $query
                ->where('social_account_id', $account->id)
                ->whereBetween('published_at', [
                    CarbonImmutable::parse($from)->startOfDay(),
                    CarbonImmutable::parse($to)->endOfDay(),
                ]))
            ->orderByDesc('views')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (PostInsight $insight): array => [
                'post_id' => $insight->postPlatform->post_id,
                'content' => Str::limit((string) $insight->postPlatform->post?->content, 120),
                'content_type' => $insight->postPlatform->content_type?->value,
                'published_at' => $insight->postPlatform->published_at?->toIso8601String(),
                'insights' => (new PostInsightResource($insight))->resolve(),
            ])
            ->all();
    }
}
