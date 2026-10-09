<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Jobs\ReadFacebookInsights;
use Database\Factories\PostPlatformFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class PostPlatform extends Model
{
    /** @use HasFactory<PostPlatformFactory> */
    use HasFactory, HasUuids;

    /** Content types that have Post insights (ADR 0004). */
    private const INSIGHTS_CONTENT_TYPES = [ContentType::FacebookPost, ContentType::FacebookReel];

    protected $fillable = [
        'post_id',
        'social_account_id',
        'enabled',
        'platform',
        'platform_name',
        'platform_username',
        'platform_avatar',
        'content_type',
        'status',
        'platform_post_id',
        'platform_url',
        'error_message',
        'error_context',
        'published_at',
        'meta',
        'connection_warning_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'platform' => SocialPlatform::class,
            'content_type' => ContentType::class,
            'status' => Status::class,
            'published_at' => 'datetime',
            'meta' => 'array',
            'error_context' => 'array',
            'connection_warning_sent_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function insight(): HasOne
    {
        return $this->hasOne(PostInsight::class);
    }

    /**
     * Only platforms still enabled for publishing — disabled ones are
     * excluded from PublishPost, so anything else that mirrors publish
     * eligibility (previews, validation, proactive checks) must too.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('post_platforms.enabled', true);
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('post_platforms.enabled', false);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('post_platforms.status', Status::Published);
    }

    /**
     * Post platforms whose Post insights are still read: a Facebook Post or
     * Reel published in the last ReadFacebookInsights::POST_DAYS days to a
     * Page that is still connected. Stories have no Post insights.
     */
    public function scopeReadsInsights(Builder $query): Builder
    {
        return $query->published()
            ->whereNotNull('post_platforms.social_account_id')
            ->whereNotNull('post_platforms.platform_post_id')
            ->whereIn('post_platforms.content_type', self::INSIGHTS_CONTENT_TYPES)
            ->where('post_platforms.published_at', '>=', now()->subDays(ReadFacebookInsights::POST_DAYS));
    }

    /**
     * The same rule as scopeReadsInsights, for one loaded Post platform.
     */
    public function readsInsights(): bool
    {
        return $this->status === Status::Published
            && $this->social_account_id !== null
            && $this->platform_post_id !== null
            && in_array($this->content_type, self::INSIGHTS_CONTENT_TYPES, true)
            && $this->published_at?->isAfter(now()->subDays(ReadFacebookInsights::POST_DAYS)) === true;
    }

    /**
     * Whether Facebook published this as a video: every Reel, and a Post
     * whose first media item is a video (FacebookPublisher picks the same way).
     */
    public function publishesVideo(): bool
    {
        if ($this->content_type === ContentType::FacebookReel) {
            return true;
        }

        return $this->post?->mediaItems->first()?->isVideo() === true;
    }

    /**
     * Get display name, falling back to snapshot if account was deleted.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->socialAccount?->accountDisplayName() ?? $this->platform_name ?? $this->platform->label();
    }

    /**
     * Get username, falling back to snapshot if account was deleted.
     */
    public function getDisplayUsernameAttribute(): ?string
    {
        return $this->socialAccount?->username ?? $this->platform_username;
    }

    /**
     * "Facebook Page (@handle)" for emails and in-app notifications.
     * Username first, then display name (live account or the snapshot
     * kept on this row). When neither is set — or the account is gone
     * and there is no snapshot — just the platform name, never "(@)".
     */
    public function notificationLabel(): string
    {
        $identifier = $this->display_username
            ?: $this->socialAccount?->display_name
            ?: $this->platform_name;

        if (! filled($identifier) || $identifier === $this->platform->label()) {
            return $this->platform->label();
        }

        return "{$this->platform->label()} (@{$identifier})";
    }

    /**
     * Get avatar URL, falling back to snapshot if account was deleted.
     */
    public function getDisplayAvatarAttribute(): ?string
    {
        if ($this->socialAccount?->avatar_url) {
            return $this->socialAccount->avatar_url;
        }

        return $this->platform_avatar ? Storage::url($this->platform_avatar) : null;
    }

    public function markAsPublishing(): void
    {
        $this->update(['status' => Status::Publishing]);
    }

    public function markAsPublished(string $platformPostId, ?string $platformUrl = null): void
    {
        $now = now();

        $this->update([
            'status' => Status::Published,
            'platform_post_id' => $platformPostId,
            'platform_url' => $platformUrl,
            'published_at' => $now,
            'error_message' => null,
            'error_context' => null,
        ]);

        $this->socialAccount?->update(['last_used_at' => $now]);
    }

    public function markAsFailed(string $errorMessage, ?array $errorContext = null): void
    {
        $this->update([
            'status' => Status::Failed,
            'error_message' => $errorMessage,
            'error_context' => $errorContext,
            'platform_post_id' => null,
            'platform_url' => null,
        ]);
    }
}
