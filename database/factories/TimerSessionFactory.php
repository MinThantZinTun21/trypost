<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Task\TimerMode;
use App\Enums\Task\TimerOutcome;
use App\Models\Task;
use App\Models\TimerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimerSession>
 */
class TimerSessionFactory extends Factory
{
    protected $model = TimerSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'workspace_id' => fn (array $attributes): string => Task::query()->findOrFail($attributes['task_id'])->workspace_id,
            'mode' => TimerMode::Free,
            'started_at' => now()->subMinutes(10),
            'ended_at' => null,
            'planned_seconds' => null,
            'outcome' => null,
        ];
    }

    public function pomodoro(int $plannedSeconds = 1500): static
    {
        return $this->state(fn (): array => ['mode' => TimerMode::Pomodoro, 'planned_seconds' => $plannedSeconds]);
    }

    public function ended(TimerOutcome $outcome = TimerOutcome::Completed): static
    {
        return $this->state(fn (): array => ['ended_at' => now(), 'outcome' => $outcome]);
    }
}
