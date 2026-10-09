<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Models\ContentIdea;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentIdea>
 */
class ContentIdeaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'title' => fake()->sentence(4),
            'details' => fake()->paragraph(),
            'status' => Status::New,
            'created_via' => CreatedVia::Web,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::InProgress,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Done,
        ]);
    }

    public function viaMcp(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_via' => CreatedVia::Mcp,
        ]);
    }
}
