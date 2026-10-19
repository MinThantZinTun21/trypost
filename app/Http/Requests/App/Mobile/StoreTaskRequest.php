<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Task\Priority;
use App\Enums\Task\ReminderType;
use App\Enums\Task\TagColor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A Task's fields from the Mobile app's editor. Updating sends the same body
 * as a full replace. A Task needs a title or a body; the body is the
 * Markdown subset of ADR 0016, stored as sent. Reminders, when sent,
 * replace the Task's set; left out, they stay as they are.
 */
class StoreTaskRequest extends FormRequest
{
    use ValidatesTaskTags;

    /** A time must carry its UTC offset. */
    private const string OFFSET = 'regex:/\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/i';

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
            'title' => ['nullable', 'required_without:description', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', 'integer', Rule::enum(Priority::class)],
            'color' => ['nullable', 'string', Rule::enum(TagColor::class)],
            'pinned' => ['sometimes', 'boolean'],
            'due_at' => ['nullable', 'date', self::OFFSET, 'before:2038-01-19'],
            'due_all_day' => ['sometimes', 'boolean'],
            'task_tags' => ['sometimes', 'array'],
            'task_tags.*' => ['required', 'uuid', 'distinct', $this->taskTagInWorkspace()],
            'reminders' => ['sometimes', 'array', 'max:10'],
            'reminders.*' => ['array'],
            'reminders.*.type' => ['required', 'string', Rule::enum(ReminderType::class)],
            'reminders.*.remind_at' => ['nullable', 'required_if:reminders.*.type,at', 'date', self::OFFSET, 'before:2038-01-19'],
            'reminders.*.minutes_before' => ['nullable', 'required_if:reminders.*.type,before_due', 'integer', 'min:0', 'max:40320'],
            'reminders.*.repeat_until_dismissed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.regex' => __('mobile.scheduled_at_offset'),
            'reminders.*.remind_at.regex' => __('mobile.scheduled_at_offset'),
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

                foreach ((array) $this->input('reminders', []) as $index => $reminder) {
                    if (data_get($reminder, 'type') === ReminderType::BeforeDue->value && blank($this->input('due_at'))) {
                        $validator->errors()->add("reminders.{$index}.minutes_before", __('tasks.reminder_needs_due'));
                    }
                }
            },
        ];
    }

    /**
     * The Task's attributes, with every left-out optional field reset.
     *
     * @return array{title: ?string, description: ?string, priority: int, color: ?string, pinned: bool, due_at: ?CarbonImmutable, due_all_day: bool}
     */
    public function taskAttributes(): array
    {
        $dueAt = $this->validated('due_at');

        return [
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'priority' => (int) $this->validated('priority', Priority::None->value),
            'color' => $this->validated('color'),
            'pinned' => $this->boolean('pinned'),
            'due_at' => $dueAt === null ? null : CarbonImmutable::parse($dueAt)->utc(),
            'due_all_day' => $dueAt !== null && $this->boolean('due_all_day'),
        ];
    }

    /**
     * The Reminders sent, or null when the request left them out.
     *
     * @return list<array{type: ReminderType, remind_at: ?CarbonImmutable, minutes_before: ?int, repeat_until_dismissed: bool}>|null
     */
    public function taskReminders(): ?array
    {
        if (! $this->has('reminders')) {
            return null;
        }

        return collect($this->validated('reminders', []))
            ->map(fn (array $reminder): array => [
                'type' => ReminderType::from(data_get($reminder, 'type')),
                'remind_at' => data_get($reminder, 'remind_at') === null ? null : CarbonImmutable::parse(data_get($reminder, 'remind_at'))->utc(),
                'minutes_before' => data_get($reminder, 'minutes_before') === null ? null : (int) data_get($reminder, 'minutes_before'),
                'repeat_until_dismissed' => (bool) data_get($reminder, 'repeat_until_dismissed', false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function taskTagIds(): array
    {
        return array_values($this->validated('task_tags', []));
    }
}
