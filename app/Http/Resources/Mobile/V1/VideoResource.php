<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Video
 */
class VideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size' => $this->file?->size,
            'duration' => data_get($this->file?->meta, 'duration'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
