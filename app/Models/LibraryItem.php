<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\MediaItem;
use App\Enums\Post\CreatedVia;
use App\Enums\PostPlatform\ContentType;
use Database\Factories\LibraryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Ready-made content in the Content library: media plus the Facebook text,
 * TikTok caption and YouTube Title, Description and Tags, kept so the Owner
 * or the Assistant can schedule Posts from it.
 */
class LibraryItem extends Model
{
    /** @use HasFactory<LibraryItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'media',
        'facebook_text',
        'tiktok_caption',
        'youtube_title',
        'youtube_description',
        'youtube_tags',
        'created_via',
    ];

    protected $attributes = [
        'media' => '[]',
        'youtube_tags' => '[]',
        'created_via' => CreatedVia::Web->value,
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'youtube_tags' => 'array',
            'created_via' => CreatedVia::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The Posts scheduled from this item.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The item's text for each field a Content type publishes: the TikTok
     * caption is a TikTok Video's caption and a TikTok Photo's description, and
     * a YouTube Short takes the YouTube Title, Description and Tags. Facebook
     * reads the Post's content instead.
     *
     * @return array<string, string|list<string>>
     */
    public function textsFor(ContentType $contentType): array
    {
        $texts = match ($contentType) {
            ContentType::TikTokVideo => ['caption' => $this->tiktok_caption],
            ContentType::TikTokPhoto => ['description' => $this->tiktok_caption],
            ContentType::YouTubeShort => [
                'title' => $this->youtube_title,
                'description' => $this->youtube_description,
                'tags' => $this->youtube_tags,
            ],
            default => [],
        };

        return array_filter($texts, fn (mixed $text): bool => filled($text));
    }

    /**
     * Newest first, with the time-ordered UUID breaking ties within a second.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->latest()->latest('id');
    }

    /**
     * The media as MediaItem DTOs, like Post::mediaItems.
     *
     * @return Attribute<Collection<int, MediaItem>, never>
     */
    protected function mediaItems(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => collect($this->media ?? [])->map(fn (array $item): MediaItem => MediaItem::fromArray($item)),
        );
    }
}
