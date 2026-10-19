<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Task\ReminderType;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'type' => ReminderType::At,
            'remind_at' => now()->addDay(),
            'minutes_before' => null,
            'repeat_until_dismissed' => false,
            'dismissed_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['dismissed_at' => now()]);
    }
}
