<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'media' => $this->media ?? [],
            'facebook_text' => $this->facebook_text,
            'tiktok_caption' => $this->tiktok_caption,
            'youtube_title' => $this->youtube_title,
            'youtube_description' => $this->youtube_description,
            'youtube_tags' => $this->youtube_tags ?? [],
            'posts_count' => $this->whenCounted('posts'),
            'created_via' => $this->created_via->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
