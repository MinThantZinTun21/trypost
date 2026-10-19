<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Task\Priority;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A Task's fields from the Mobile app's editor. Updating sends the same body
 * as a full replace.
 */
class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', 'integer', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date', 'regex:/\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/i', 'before:2038-01-19'],
            'due_all_day' => ['sometimes', 'boolean'],
            'task_tags' => ['sometimes', 'array'],
            'task_tags.*' => ['required', 'uuid', 'distinct', $this->taskTagInWorkspace()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.regex' => __('mobile.scheduled_at_offset'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('due_all_day') && blank($this->input('due_at'))) {
                    $validator->errors()->add('due_all_day', __('tasks.all_day_needs_due'));
                }
            },
        ];
    }

    /**
     * The Task's attributes, with every left-out optional field reset.
     *
     * @return array{title: string, description: ?string, priority: int, due_at: ?CarbonImmutable, due_all_day: bool}
     */
    public function taskAttributes(): array
    {
        $dueAt = $this->validated('due_at');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'priority' => (int) $this->validated('priority', Priority::None->value),
            'due_at' => $dueAt === null ? null : CarbonImmutable::parse($dueAt)->utc(),
            'due_all_day' => $dueAt !== null && $this->boolean('due_all_day'),
        ];
    }

    /**
     * @return list<string>
     */
    public function taskTagIds(): array
    {
        return array_values($this->validated('task_tags', []));
    }
}
