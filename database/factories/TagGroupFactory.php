<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Task\TagColor;
use App\Models\TagGroup;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagGroup>
 */
class TagGroupFactory extends Factory
{
    protected $model = TagGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->randomElement(TagColor::cases()),
        ];
    }
}
