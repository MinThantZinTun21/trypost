<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagGroup;
use App\Models\TaskTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTag>
 */
class TaskTagFactory extends Factory
{
    protected $model = TaskTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tag_group_id' => TagGroup::factory(),
            'name' => $this->faker->unique()->word(),
        ];
    }
}
