<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\TagGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TagGroup
 */
class TagGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color->value,
            'task_tags' => TaskTagResource::collection($this->taskTags->sortBy('name')->values()),
        ];
    }
}
