<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostInsightFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifetime Post insights for one Post platform published to a Facebook Page
 * (ADR 0004).
 */
class PostInsight extends Model
{
    /** @use HasFactory<PostInsightFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_platform_id',
        'feed_post_id',
        'views',
        'reach',
        'reactions',
        'clicks',
        'video_views',
        'avg_watch_time_ms',
        'watch_time_ms',
        'reel_plays',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'reach' => 'integer',
            'reactions' => 'integer',
            'clicks' => 'integer',
            'video_views' => 'integer',
            'avg_watch_time_ms' => 'integer',
            'watch_time_ms' => 'integer',
            'reel_plays' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function postPlatform(): BelongsTo
    {
        return $this->belongsTo(PostPlatform::class);
    }
}
