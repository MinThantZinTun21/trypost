<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Dto\MediaItem;
use App\Models\Post;
use App\Support\PostStatusRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Post for the Mobile app (ADR 0010). Times are ISO 8601 in UTC; the app
 * shows them in the phone's timezone.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'content' => (string) $this->content,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'editable' => ! PostStatusRules::blocksEditing($this->resource),
            'media' => $this->mediaItems->map(fn (MediaItem $item): array => [
                'id' => $item->id,
                'type' => $item->type?->value,
                'url' => $item->url,
                'mime_type' => $item->mime_type,
                'original_filename' => $item->original_filename,
            ])->values()->all(),
            'platforms' => PostPlatformResource::collection($this->whenLoaded('postPlatforms')),
        ];
    }
}
