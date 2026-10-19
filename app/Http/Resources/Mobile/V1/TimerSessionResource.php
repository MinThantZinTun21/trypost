<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\TimerSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Timer session. `elapsed_seconds` is as of the response; the phone keeps
 * counting from `started_at`. The running timer also names its Task.
 *
 * @mixin TimerSession
 */
class TimerSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'mode' => $this->mode->value,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'planned_seconds' => $this->planned_seconds,
            'outcome' => $this->outcome?->value,
            'elapsed_seconds' => $this->elapsedSeconds(),
            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'description' => $this->task->description,
            ]),
        ];
    }
}
