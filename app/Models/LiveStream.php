<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LiveStream\Privacy;
use App\Enums\LiveStream\Status;
use Database\Factories\LiveStreamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Video played on repeat as a live broadcast on one YouTube Social account
 * (ADR 0017). It is not a Post. The app creates and binds the broadcast
 * through the YouTube Live API; the `live` worker plays the Video into its
 * ingest URL with ffmpeg. Only one runs at a time.
 */
class LiveStream extends Model
{
    /** @use HasFactory<LiveStreamFactory> */
    use HasFactory, HasUuids;

    /** A heartbeat older than this means no worker is playing the Live stream. */
    public const int STALE_AFTER_SECONDS = 120;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'video_id',
        'social_account_id',
        'title',
        'description',
        'privacy',
        'status',
        'starts_at',
        'stops_at',
        'started_at',
        'ended_at',
        'heartbeat_at',
        'youtube_broadcast_id',
        'youtube_stream_id',
        'ingest_url',
        'failure_reason',
    ];

    protected $hidden = [
        'ingest_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privacy' => Privacy::class,
            'status' => Status::class,
            'starts_at' => 'datetime',
            'stops_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'ingest_url' => 'encrypted',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * @param  Builder<LiveStream>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', Status::active());
    }

    /**
     * @param  Builder<LiveStream>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', Status::Live);
    }

    /**
     * Whether a Live stream other than $except is live, or is being readied
     * by the live worker (claimed with a fresh heartbeat). Only one plays at
     * a time on this install.
     */
    public static function anotherIsPlaying(?LiveStream $except = null): bool
    {
        return self::query()
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->where(fn (Builder $query) => $query
                ->where('status', Status::Live)
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', Status::Scheduled)
                    ->where('heartbeat_at', '>=', now()->subSeconds(self::STALE_AFTER_SECONDS))))
            ->exists();
    }

    /**
     * Where the live worker keeps its copy of the Video while it plays.
     */
    public function localVideoPath(): string
    {
        return storage_path("app/live/{$this->id}.mp4");
    }

    /**
     * The broadcast's page on YouTube.
     */
    public function watchUrl(): ?string
    {
        return $this->youtube_broadcast_id === null ? null : "https://www.youtube.com/watch?v={$this->youtube_broadcast_id}";
    }
}
