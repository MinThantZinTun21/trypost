<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LiveStream
 */
class LiveStreamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'title' => $this->title,
            'privacy' => $this->privacy->value,
            'video_name' => $this->video?->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'stops_at' => $this->stops_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'watch_url' => $this->watchUrl(),
        ];
    }
}
