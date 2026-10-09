<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PageInsightSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Page insights read for one Facebook Social account on one day (ADR 0004).
 */
class PageInsightSnapshot extends Model
{
    /** @use HasFactory<PageInsightSnapshotFactory> */
    use HasFactory, HasUuids;

    /** Facebook counts Insights days in Pacific time. */
    public const DAY_TIMEZONE = 'America/Los_Angeles';

    protected $fillable = [
        'social_account_id',
        'date',
        'followers',
        'new_follows',
        'unfollows',
        'views',
        'reach',
        'engagements',
        'video_views',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'followers' => 'integer',
            'new_follows' => 'integer',
            'unfollows' => 'integer',
            'views' => 'integer',
            'reach' => 'integer',
            'engagements' => 'integer',
            'video_views' => 'integer',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * The newest day Facebook has finished counting: yesterday in Pacific
     * time, which is two UTC days back until Pacific midnight (07:00 or 08:00
     * UTC). Returned as that calendar date at midnight in the app's timezone.
     */
    public static function lastCompleteDay(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::DAY_TIMEZONE)->subDay()->toDateString());
    }
}
