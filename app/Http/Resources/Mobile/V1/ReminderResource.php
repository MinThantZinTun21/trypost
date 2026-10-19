<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Reminder, with its Task's title, body and due when the upcoming list
 * loaded it, so the phone can word the notification offline.
 *
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'type' => $this->type->value,
            'remind_at' => $this->remind_at->toIso8601String(),
            'minutes_before' => $this->minutes_before,
            'repeat_until_dismissed' => $this->repeat_until_dismissed,
            'dismissed_at' => $this->dismissed_at?->toIso8601String(),
            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'description' => $this->task->description,
                'due_at' => $this->task->due_at?->toIso8601String(),
                'due_all_day' => $this->task->due_all_day,
            ]),
        ];
    }
}
