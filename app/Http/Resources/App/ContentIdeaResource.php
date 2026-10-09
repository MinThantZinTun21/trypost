<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentIdeaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt(),
            'status' => $this->status->value,
            'created_via' => $this->created_via->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
