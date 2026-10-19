<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Task\TimerMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Pomodoro carries its length from the app's Settings; a free timer has
 * none.
 */
class StartTimerRequest extends FormRequest
{
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
            'mode' => ['required', 'string', Rule::enum(TimerMode::class)],
            'planned_seconds' => ['nullable', 'required_if:mode,pomodoro', 'prohibited_if:mode,free', 'integer', 'min:60', 'max:14400'],
        ];
    }

    public function mode(): TimerMode
    {
        return TimerMode::from((string) $this->validated('mode'));
    }

    public function plannedSeconds(): ?int
    {
        $plannedSeconds = $this->validated('planned_seconds');

        return $plannedSeconds === null ? null : (int) $plannedSeconds;
    }
}
