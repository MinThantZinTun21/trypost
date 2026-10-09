<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Notification\Type;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Exceptions\SocialAccount\NetworkAlreadyConnectedException;
use App\Jobs\ReadFacebookInsights;
use App\Jobs\SendNotification;
use Carbon\CarbonImmutable;
use Database\Factories\SocialAccountFactory;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'platform',
        'platform_user_id',
        'username',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'meta',
        'status',
        'is_active',
        'error_message',
        'disconnected_at',
        'last_used_at',
        'last_verified_at',
        'insights_read_at',
        'insights_attempted_at',
        'insights_refresh_queued_at',
        'insights_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'meta',
    ];

    protected $appends = [
        'display_label',
        'handle_label',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => Status::class,
            'is_active' => 'boolean',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'insights_read_at' => 'datetime',
            'insights_attempted_at' => 'datetime',
            'insights_refresh_queued_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Persist a freshly authorized identity.
     *
     * A reconnect only reuses its row when the provider returned the very same
     * identity. Authorizing a different account is refused instead of repointing
     * the card (and every post scheduled against it) at a stranger.
     *
     * @param  array<string, mixed>  $values
     */
    public static function connectIdentity(
        Workspace $workspace,
        SocialPlatform $platform,
        string $platformUserId,
        array $values,
        ?self $reconnect = null,
    ): self {
        // Two popups finishing at once for the same network must not interleave
        // a reconnect's update with a fresh insert.
        try {
            return Cache::lock("social_connect:{$workspace->id}:{$platform->network()}", 10)
                ->block(5, fn (): self => static::persistIdentity(
                    $workspace,
                    $platform,
                    $platformUserId,
                    $values,
                    $reconnect,
                ));
        } catch (LockTimeoutException) {
            throw NetworkAlreadyConnectedException::connectInProgress($platform);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function persistIdentity(
        Workspace $workspace,
        SocialPlatform $platform,
        string $platformUserId,
        array $values,
        ?self $reconnect,
    ): self {
        $values['platform'] = $platform;
        $values['platform_user_id'] = $platformUserId;

        $identity = [
            'platform' => $platform->value,
            'platform_user_id' => $platformUserId,
        ];

        if (
            $reconnect?->workspace_id === $workspace->id
            && $reconnect->platform->network() === $platform->network()
        ) {
            if ((string) $reconnect->platform_user_id !== $platformUserId) {
                throw NetworkAlreadyConnectedException::identityMismatch($platform);
            }

            try {
                $reconnect->update($values);
            } catch (UniqueConstraintViolationException) {
                throw new NetworkAlreadyConnectedException($platform);
            }

            return $reconnect;
        }

        try {
            return $workspace->socialAccounts()->updateOrCreate($identity, $values);
        } catch (UniqueConstraintViolationException) {
            $account = $workspace->socialAccounts()->where($identity)->firstOrFail();
            $account->update($values);

            return $account;
        }
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }

    public function pageInsightSnapshots(): HasMany
    {
        return $this->hasMany(PageInsightSnapshot::class);
    }

    /**
     * Whether an Insights refresh is queued and still has time to finish. A
     * mark older than the job's unique lock belongs to a read that died, so it
     * stops blocking (and polling) instead of hanging forever.
     */
    public function insightsRefreshPending(): bool
    {
        return $this->insights_refresh_queued_at !== null
            && $this->insights_refresh_queued_at->isAfter(now()->subSeconds(ReadFacebookInsights::UNIQUE_FOR_SECONDS));
    }

    /**
     * When the Owner may next ask for an Insights refresh, or null for now.
     * Refresh now is allowed once an hour after the last read started (ADR
     * 0004), whether it succeeded or not: a failed read spent the rate limit too.
     */
    public function insightsRefreshAvailableAt(): ?CarbonImmutable
    {
        $availableAt = $this->insights_attempted_at?->addHour();

        return $availableAt !== null && $availableAt->isFuture() ? $availableAt : null;
    }

    protected function isTokenExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->token_expires_at && $this->token_expires_at->isPast(),
        );
    }

    protected function isTokenExpiringSoon(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->token_expires_at && $this->token_expires_at->isBefore(now()->addMinutes(15)),
        );
    }

    /**
     * Whether the token should be refreshed before use. Rotating-refresh-token
     * platforms are only refreshed once actually expired, to avoid rotating a
     * still-valid single-use refresh_token.
     */
    public function needsProactiveTokenRefresh(): bool
    {
        return $this->is_token_expired;
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::url($value) : null,
        );
    }

    protected function profileUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $username = $this->username;
                $platformUserId = $this->platform_user_id;

                return match ($this->platform) {
                    SocialPlatform::Facebook => ($username || $platformUserId)
                        ? 'https://facebook.com/'.($username ?: $platformUserId)
                        : null,
                    SocialPlatform::TikTok => $username ? "https://tiktok.com/@{$username}" : null,
                    SocialPlatform::YouTube => $username ? "https://youtube.com/@{$username}" : null,
                };
            },
        );
    }

    /**
     * "@handle" for notification bodies — the more specific identifier
     * (username) wins over the friendlier display name when both are set.
     * Connectors normally populate at least one of username/display_name
     * (TikTok Login Kit still returns display_name via user.info.basic;
     * username needs user.info.profile, which self-hosters may trim).
     * The platform label is a last-resort fallback, not an expected path.
     */
    public function handle(): string
    {
        return '@'.($this->username ?: $this->display_name ?: $this->platform->label());
    }

    /**
     * Friendly label for email templates — the display name wins over the
     * username when both are set.
     */
    public function accountDisplayName(): string
    {
        return $this->display_name ?: $this->username ?: $this->platform->label();
    }

    /**
     * Frontend-facing mirror of accountDisplayName() — appended to JSON so
     * Vue components stop re-implementing this fallback.
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->accountDisplayName(),
        );
    }

    /**
     * Frontend-facing mirror of handle() without the "@" prefix — templates
     * that render their own "@" (e.g. platform previews) use this instead.
     */
    protected function handleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->username ?: $this->display_name ?: $this->platform->label(),
        );
    }

    public function markAsDisconnected(string $errorMessage): void
    {
        $lock = Cache::lock("social_account_status:{$this->id}", 10);

        if ($lock->get()) {
            try {
                $this->refresh();
                $wasConnected = $this->status !== Status::Disconnected;

                $this->update([
                    'status' => Status::Disconnected,
                    'error_message' => $errorMessage,
                    'disconnected_at' => now(),
                ]);

                if ($wasConnected && $this->workspace->owner) {
                    $placeholders = [
                        'platform' => $this->platform->label(),
                        'account' => $this->handle(),
                    ];

                    SendNotification::dispatch(
                        user: $this->workspace->owner,
                        workspaceId: $this->workspace_id,
                        type: Type::AccountDisconnected,
                        title: __('notifications.account_disconnected.title', $placeholders),
                        body: __('notifications.account_disconnected.body', $placeholders),
                        data: ['social_account_id' => $this->id],
                    );
                }
            } finally {
                $lock->release();
            }
        }
    }

    public function markAsTokenExpired(string $errorMessage, bool $notify = true): void
    {
        $lock = Cache::lock("social_account_status:{$this->id}", 10);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->refresh();
            $wasUsable = $this->status === Status::Connected;

            $this->update([
                'status' => Status::TokenExpired,
                'error_message' => $errorMessage,
                'disconnected_at' => $this->disconnected_at ?? now(),
            ]);

            if ($notify && $wasUsable && $this->workspace->owner) {
                $placeholders = [
                    'platform' => $this->platform->label(),
                    'account' => $this->handle(),
                ];

                SendNotification::dispatch(
                    user: $this->workspace->owner,
                    workspaceId: $this->workspace_id,
                    type: Type::AccountDisconnected,
                    title: __('notifications.account_token_expired.title', $placeholders),
                    body: __('notifications.account_token_expired.body', $placeholders),
                    data: ['social_account_id' => $this->id],
                );
            }
        } finally {
            $lock->release();
        }
    }

    public function markAsConnected(): void
    {
        $this->update([
            'status' => Status::Connected,
            'error_message' => null,
            'disconnected_at' => null,
        ]);
    }

    public function isDisconnected(): bool
    {
        return $this->status === Status::Disconnected || $this->status === Status::TokenExpired;
    }

    /**
     * Facebook Pages, oldest connected first: the accounts that have Insights.
     */
    public function scopeFacebookPages(Builder $query): Builder
    {
        return $query->where('platform', SocialPlatform::Facebook)->orderBy('created_at')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('platform');
    }
}
