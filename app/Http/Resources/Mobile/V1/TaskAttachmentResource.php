<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Dto\MediaItem;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Task attachment, with the same fields as a Post's media item plus its
 * size and a video's or audio's length.
 *
 * @mixin Media
 */
class TaskAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = MediaItem::fromMedia($this->resource);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->original_filename,
            'size' => $this->size,
            'duration' => data_get($this->meta, 'duration'),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $item->downloadUrl(),
            'file_name' => $item->filename(),
        ];
    }
}
