<?php

declare(strict_types=1);

namespace App\Enums\PostPlatform;

use App\Dto\MediaItem;
use App\Enums\Media\Type as MediaType;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use Illuminate\Support\Collection;

enum ContentType: string
{
    // Facebook
    case FacebookPost = 'facebook_post';
    case FacebookReel = 'facebook_reel';
    case FacebookStory = 'facebook_story';

    // TikTok
    case TikTokVideo = 'tiktok_video';
    case TikTokPhoto = 'tiktok_photo';

    // YouTube
    case YouTubeShort = 'youtube_short';

    public function label(): string
    {
        return match ($this) {
            self::FacebookPost => 'Post',
            self::FacebookReel => 'Reel',
            self::FacebookStory => 'Story',
            self::TikTokVideo => 'Video',
            self::TikTokPhoto => 'Photo carousel',
            self::YouTubeShort => 'Short',
        };
    }

    /**
     * The per-account text (PostPlatform meta keys) this content type
     * publishes; the content stands in for any of them left blank.
     *
     * @return list<string>
     */
    public function textFields(): array
    {
        return match ($this) {
            self::FacebookReel, self::TikTokPhoto, self::YouTubeShort => ['title', 'description'],
            self::TikTokVideo => ['caption'],
            self::FacebookPost, self::FacebookStory => [],
        };
    }

    public function description(): string
    {
        return (string) trans("posts.content_types.{$this->value}.description");
    }

    public function platform(): SocialPlatform
    {
        return match ($this) {
            self::FacebookPost, self::FacebookReel, self::FacebookStory => SocialPlatform::Facebook,
            self::TikTokVideo, self::TikTokPhoto => SocialPlatform::TikTok,
            self::YouTubeShort => SocialPlatform::YouTube,
        };
    }

    /**
     * Target image dimensions for this format.
     *
     * @return array{width: int, height: int}
     */
    public function imageDimensions(): array
    {
        return match ($this) {
            // Square 1:1
            self::FacebookPost => ['width' => 1080, 'height' => 1080],

            // Stories 9:16
            self::FacebookStory => ['width' => 1080, 'height' => 1920],

            // Default: 4:5 portrait (used for any other case)
            default => ['width' => 1080, 'height' => 1350],
        };
    }

    public function aspectRatio(): ?string
    {
        return match ($this) {
            self::FacebookReel, self::FacebookStory => '9:16',
            self::TikTokVideo, self::YouTubeShort => '9:16',
            self::TikTokPhoto => '1:1',
            default => null,
        };
    }

    public function maxMediaCount(): int
    {
        return match ($this) {
            self::FacebookPost => 10,
            self::FacebookReel, self::FacebookStory => 1,
            self::TikTokVideo => 1,
            self::TikTokPhoto => 35,
            self::YouTubeShort => 1,
        };
    }

    /**
     * Maximum video duration in seconds for this content type, when the
     * platform publishes a hard cap via API. Null when unlimited or unknown.
     * TikTok's `creator_info` may lower the 10 min ceiling per account; the
     * editor reads it per account and TikTok rejects longer uploads itself.
     *
     * Single source of truth for the web editor (via Inertia shared props).
     */
    public function maxVideoDurationSec(): ?int
    {
        return match ($this) {
            self::FacebookPost => 240 * 60,
            self::FacebookReel => 90,
            self::FacebookStory => 60,
            self::YouTubeShort => 3 * 60,
            self::TikTokVideo => 10 * 60,
            default => null,
        };
    }

    /**
     * Minimum attachments required (0 = no floor beyond requiresMedia()).
     */
    public function minMediaCount(): int
    {
        return match ($this) {
            self::TikTokPhoto => 1,
            default => 0,
        };
    }

    /**
     * Whether animated GIFs are accepted (kept as GIF, not flattened to JPEG).
     * None of the kept networks accept them as-is.
     */
    public function acceptsGif(): bool
    {
        return false;
    }

    /**
     * Every network we publish to accepts QuickTime/MOV.
     */
    public function acceptsMov(): bool
    {
        return true;
    }

    /**
     * Per-type image size cap in bytes, capped at the global upload hard limit
     * (trypost.media.max_size_mb.image). Null when images are not accepted or
     * the platform has no tighter editor-side limit than that hard cap.
     */
    public function maxImageBytes(): ?int
    {
        $bytes = match ($this) {
            self::FacebookPost => self::bytesFromMb(4),
            self::TikTokPhoto => self::bytesFromMb(20),
            default => null,
        };

        return self::capToHardLimit($bytes, MediaType::Image);
    }

    /**
     * Per-type video size cap in bytes, capped at the global upload hard limit
     * (trypost.media.max_size_mb.video). Null when videos are not accepted or
     * the platform has no tighter editor-side limit than that hard cap.
     */
    public function maxVideoBytes(): ?int
    {
        $bytes = match ($this) {
            self::FacebookPost => self::bytesFromGb(10),
            self::FacebookReel, self::FacebookStory => self::bytesFromGb(1),
            self::YouTubeShort => self::bytesFromGb(256),
            self::TikTokVideo => self::bytesFromGb(4),
            default => null,
        };

        return self::capToHardLimit($bytes, MediaType::Video);
    }

    /**
     * Per-type PDF size cap in bytes. No kept content type accepts documents.
     */
    public function maxDocumentBytes(): ?int
    {
        return null;
    }

    /**
     * Soft aspect-ratio window used by the Vue cropper / media picker.
     *
     * @return array{min: float, max: float}|null
     */
    public function aspectRatioBounds(): ?array
    {
        return match ($this) {
            self::FacebookReel, self::FacebookStory,
            self::YouTubeShort => ['min' => 0.5, 'max' => 0.6],
            default => null,
        };
    }

    /**
     * Whether the editor should auto-fit still images into the story frame.
     */
    public function autoFitsImage(): bool
    {
        return false;
    }

    /**
     * Full media-rule payload for the Vue editor (and any other consumer).
     * Shared once via Inertia — do not re-hardcode these numbers in JS.
     *
     * @return array{
     *     max_files: int,
     *     min_files: int|null,
     *     accept_images: bool,
     *     accept_videos: bool,
     *     accept_documents: bool,
     *     requires_media: bool,
     *     accepts_gif: bool,
     *     accepts_mov: bool,
     *     forbids_mixed_media: bool,
     *     max_image_bytes: int|null,
     *     max_video_bytes: int|null,
     *     max_document_bytes: int|null,
     *     max_video_duration_sec: int|null,
     *     aspect_ratio_min: float|null,
     *     aspect_ratio_max: float|null,
     *     auto_fits_image: bool
     * }
     */
    public function mediaRules(): array
    {
        $bounds = $this->aspectRatioBounds();
        $minFiles = $this->minMediaCount();

        return [
            'max_files' => $this->maxMediaCount(),
            'min_files' => $minFiles > 0 ? $minFiles : null,
            'accept_images' => $this->supportsImage(),
            'accept_videos' => $this->supportsVideo(),
            'accept_documents' => $this->supportsDocument(),
            'requires_media' => $this->requiresMedia(),
            'accepts_gif' => $this->acceptsGif(),
            'accepts_mov' => $this->acceptsMov(),
            'forbids_mixed_media' => ! $this->supportsMixedMedia(),
            'max_image_bytes' => $this->maxImageBytes(),
            'max_video_bytes' => $this->maxVideoBytes(),
            'max_document_bytes' => $this->maxDocumentBytes(),
            'max_video_duration_sec' => $this->maxVideoDurationSec(),
            'aspect_ratio_min' => $bounds['min'] ?? null,
            'aspect_ratio_max' => $bounds['max'] ?? null,
            'auto_fits_image' => $this->autoFitsImage(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function mediaRulesForFrontend(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->mediaRules()])
            ->all();
    }

    private static function bytesFromMb(int $megabytes): int
    {
        return $megabytes * 1024 * 1024;
    }

    private static function bytesFromGb(int $gigabytes): int
    {
        return $gigabytes * 1024 * 1024 * 1024;
    }

    /**
     * Never advertise a soft platform ceiling above what trypost.media allows
     * on upload (web / API / MCP signed URL).
     */
    private static function capToHardLimit(?int $platformBytes, MediaType $type): ?int
    {
        if ($platformBytes === null) {
            return null;
        }

        return min($platformBytes, $type->maxSizeInBytes());
    }

    public function supportsVideo(): bool
    {
        return match ($this) {
            self::FacebookPost, self::FacebookReel, self::FacebookStory => true,
            self::TikTokVideo => true,
            self::TikTokPhoto => false,
            self::YouTubeShort => true,
        };
    }

    public function supportsImage(): bool
    {
        return match ($this) {
            self::FacebookReel, self::FacebookStory => false,
            self::TikTokVideo => false,
            self::TikTokPhoto => true,
            self::YouTubeShort => false,
            default => true,
        };
    }

    /**
     * Whether this content type can carry a PDF document. No kept content type
     * does.
     */
    public function supportsDocument(): bool
    {
        return false;
    }

    /**
     * Whether a single post may carry images and a video together.
     */
    public function supportsMixedMedia(): bool
    {
        return true;
    }

    /**
     * Whether this content type carries a text caption visible to viewers.
     * Stories are image-overlay only — viewers don't see a separate caption.
     */
    public function supportsCaption(): bool
    {
        return match ($this) {
            self::FacebookStory => false,
            default => true,
        };
    }

    public function requiresMedia(): bool
    {
        return match ($this) {
            self::FacebookPost => false,
            default => true,
        };
    }

    /**
     * Platforms this content_type can be assigned to.
     *
     * @return array<SocialPlatform>
     */
    public function compatiblePlatforms(): array
    {
        return [$this->platform()];
    }

    /**
     * Get all content types for a specific platform.
     *
     * @return array<self>
     */
    public static function forPlatform(SocialPlatform $platform): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => $type->platform() === $platform
        );
    }

    /**
     * The default content type for a platform and the media a Post carries:
     * a Reel, TikTok Video or Short for a video, a Post or TikTok Photo for
     * images, and the platform's default without media.
     *
     * @param  Collection<int, MediaItem>  $mediaItems
     */
    public static function defaultForMedia(SocialPlatform $platform, Collection $mediaItems): self
    {
        if ($mediaItems->isEmpty()) {
            return self::defaultFor($platform);
        }

        $hasVideo = $mediaItems->contains(fn (MediaItem $item): bool => $item->isVideo());

        return match ($platform) {
            SocialPlatform::Facebook => $hasVideo ? self::FacebookReel : self::FacebookPost,
            SocialPlatform::TikTok => $hasVideo ? self::TikTokVideo : self::TikTokPhoto,
            SocialPlatform::YouTube => self::YouTubeShort,
        };
    }

    /**
     * Get the default content type for a platform.
     */
    public static function defaultFor(SocialPlatform $platform): self
    {
        return match ($platform) {
            SocialPlatform::Facebook => self::FacebookPost,
            SocialPlatform::TikTok => self::TikTokVideo,
            SocialPlatform::YouTube => self::YouTubeShort,
        };
    }
}
