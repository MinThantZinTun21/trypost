<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Task\Status;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'title' => $this->faker->sentence(4),
            'description' => null,
            'status' => Status::Todo,
            'priority' => 0,
            'color' => null,
            'pinned' => false,
            'due_at' => null,
            'due_all_day' => false,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => Status::Done, 'completed_at' => now()]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => Status::InProgress]);
    }
}
