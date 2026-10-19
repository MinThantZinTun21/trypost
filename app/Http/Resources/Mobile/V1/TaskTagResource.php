<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\TaskTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskTag
 */
class TaskTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tag_group_id' => $this->tag_group_id,
        ];
    }
}
