<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

use App\Enums\Media\Type as MediaType;
use App\Enums\TikTok\PrivacyLevel;

enum Platform: string
{
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';

    /**
     * The network a platform belongs to. Each kept platform is its own network;
     * the accounts UI groups by this value.
     */
    public function network(): string
    {
        return $this->value;
    }

    /**
     * @return array<int, string>
     */
    public function networkPlatformValues(): array
    {
        return array_values(array_map(
            fn (self $platform): string => $platform->value,
            array_filter(self::cases(), fn (self $platform): bool => $platform->network() === $this->network()),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube Shorts',
            self::Facebook => 'Facebook Page',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TikTok => '#000000',
            self::YouTube => '#FF0000',
            self::Facebook => '#1877F2',
        };
    }

    public function allowedMediaTypes(): array
    {
        return match ($this) {
            self::TikTok => [MediaType::Video],
            self::YouTube => [MediaType::Video],
            self::Facebook => [MediaType::Image, MediaType::Video],
        };
    }

    public function maxImages(): int
    {
        return match ($this) {
            self::TikTok => 0,
            self::YouTube => 0,
            self::Facebook => 10,
        };
    }

    /**
     * Character cap the platform's API accepts for image alt text (accessibility
     * description), or null when the platform has no alt-text field. Facebook
     * documents no limit, so a defensive cap is used instead. Single source of
     * truth — publishers truncate to this value, never a literal.
     */
    public function altTextMaxLength(): ?int
    {
        return match ($this) {
            self::Facebook => 1000,
            self::TikTok, self::YouTube => null,
        };
    }

    /**
     * Whether the platform's API accepts image alt text (accessibility
     * description) on published media.
     */
    public function supportsAltText(): bool
    {
        return $this->altTextMaxLength() !== null;
    }

    /**
     * Hard cap (in characters) the platform's API will accept. Going over this
     * means the post can't be published. Values are the documented API maxes:
     *
     *  - TikTok caption: 2200
     *  - YouTube Shorts: content supplies the title, capped at 100 characters
     *    (publisher derives it from the first line via `buildTitle`). Optional
     *    meta.description is separate plain text, capped at 5000 UTF-8 bytes;
     *    absent descriptions fall back to content.
     *  - Facebook text status: 10000 (API allows 63206; we cap below
     *    that — 63k-char posts are unrealistic and emoji-heavy content
     *    risks overflowing the TEXT column's 65535-byte ceiling)
     */
    public function maxContentLength(): int
    {
        return match ($this) {
            self::TikTok => 2200,
            self::YouTube => 100,
            self::Facebook => 10000,
        };
    }

    /**
     * Number of characters by which the given content exceeds this platform's
     * hard cap. Returns 0 when it fits. Single source of truth for content-
     * length checks — used both at schedule-validation time and at publish
     * time itself so the two paths can never drift apart.
     */
    public function contentOverflow(string $content): int
    {
        return max(0, mb_strlen($content) - $this->maxContentLength());
    }

    /**
     * @return array<string>
     */
    public function requiredPublishScopes(): array
    {
        return match ($this) {
            self::Facebook => ['pages_manage_posts'],
            self::TikTok => ['video.publish'],
            self::YouTube => ['https://www.googleapis.com/auth/youtube.upload'],
        };
    }

    public function supportsTextOnly(): bool
    {
        return match ($this) {
            self::TikTok => false,
            self::YouTube => false,
            self::Facebook => true,
        };
    }

    public function requiresContent(): bool
    {
        return match ($this) {
            self::YouTube => true,
            default => false,
        };
    }

    /**
     * Whether ConnectionVerifier has a real per-account token refresh flow
     * for this platform. Facebook uses Page tokens that don't expire, so a
     * rejected verify call can't be retried after a refresh — there's nothing
     * to refresh.
     */
    public function hasTokenRefreshFlow(): bool
    {
        return match ($this) {
            self::YouTube, self::TikTok => true,
            default => false,
        };
    }

    public function queue(): string
    {
        return 'social-'.$this->value;
    }

    /**
     * @return array<string>
     */
    public static function allQueues(): array
    {
        return array_map(fn (self $platform) => $platform->queue(), self::cases());
    }

    /**
     * @return array<string>
     */
    public static function enabledQueues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $platform): bool => $platform->isEnabled())
            ->map(fn (self $platform): string => $platform->queue())
            ->values()
            ->all();
    }

    public function isEnabled(): bool
    {
        return (bool) config(
            "trypost.platforms.{$this->value}.enabled",
            env(match ($this) {
                self::TikTok => 'TIKTOK_ENABLED',
                self::YouTube => 'YOUTUBE_ENABLED',
                self::Facebook => 'FACEBOOK_ENABLED',
            }, true),
        );
    }

    /**
     * Whether this platform gets its own "Connect" card in the accounts grid.
     */
    public function isConnectable(): bool
    {
        return $this->isEnabled();
    }

    /**
     * @return list<array{value: string, label: string, network: string}>
     */
    public static function connectableOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $platform): bool => $platform->isConnectable())
            ->sortBy(fn (self $platform): string => mb_strtolower($platform->label()))
            ->map(fn (self $platform): array => [
                'value' => $platform->value,
                'label' => $platform->label(),
                'network' => $platform->network(),
            ])
            ->values()
            ->all();
    }

    /**
     * Static, platform-specific data exposed to the frontend (e.g. TikTok privacy options,
     * compliance URLs). Returns an empty array for platforms with no extra config.
     *
     * @return array<string, mixed>
     */
    public function publishConfig(): array
    {
        return match ($this) {
            self::TikTok => [
                'privacyLevelOptions' => PrivacyLevel::values(),
                'musicUsageConfirmationUrl' => 'https://www.tiktok.com/legal/page/global/music-usage-confirmation/en',
                'brandedContentPolicyUrl' => 'https://www.tiktok.com/legal/page/global/bc-policy/en',
            ],
            default => [],
        };
    }
}
