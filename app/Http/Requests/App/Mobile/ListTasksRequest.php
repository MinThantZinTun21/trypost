<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Mobile\TaskTab;
use App\Models\TaskTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    use ValidatesTaskTags;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['required', 'string', Rule::enum(TaskTab::class)],
            'task_tags' => ['nullable', 'array'],
            'task_tags.*' => ['required', 'uuid', $this->taskTagInWorkspace()],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function tab(): TaskTab
    {
        return TaskTab::from((string) $this->validated('tab'));
    }

    /**
     * The picked Task tag ids keyed by their Tag group id, so the list keeps
     * Tasks with one of each group's picks.
     *
     * @return array<string, list<string>>
     */
    public function taskTagIdsByGroup(): array
    {
        return TaskTag::query()
            ->whereKey($this->validated('task_tags', []))
            ->get(['id', 'tag_group_id'])
            ->groupBy('tag_group_id')
            ->map(fn ($taskTags): array => $taskTags->pluck('id')->all())
            ->all();
    }

    public function search(): ?string
    {
        $text = trim((string) $this->validated('q'));

        return $text === '' ? null : $text;
    }
}
