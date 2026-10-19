<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\Task;
use App\Models\TaskTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'color' => $this->color?->value,
            'pinned' => $this->pinned,
            'due_at' => $this->due_at?->toIso8601String(),
            'due_all_day' => $this->due_all_day,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'task_tags' => $this->taskTags
                ->sortBy([['tagGroup.name', 'asc'], ['name', 'asc']])
                ->map(fn (TaskTag $taskTag): array => [
                    'id' => $taskTag->id,
                    'name' => $taskTag->name,
                    'tag_group_id' => $taskTag->tag_group_id,
                    'tag_group_name' => $taskTag->tagGroup->name,
                    'color' => $taskTag->tagGroup->color->value,
                ])
                ->values()
                ->all(),
            'reminders' => ReminderResource::collection($this->reminders),
        ];
    }
}
