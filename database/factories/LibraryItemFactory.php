<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Dto\MediaItem;
use App\Enums\Post\CreatedVia;
use App\Models\LibraryItem;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryItem>
 */
class LibraryItemFactory extends Factory
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
            'name' => fake()->sentence(3),
            'media' => [],
            'facebook_text' => fake()->paragraph(),
            'tiktok_caption' => fake()->sentence(),
            'youtube_title' => fake()->sentence(4),
            'youtube_description' => fake()->paragraph(),
            'youtube_tags' => fake()->words(3),
            'created_via' => CreatedVia::Web,
        ];
    }

    /**
     * @param  list<Media>  $media
     */
    public function withMedia(array $media): static
    {
        return $this->state(fn (array $attributes) => [
            'media' => collect($media)->map(fn (Media $item): array => MediaItem::fromMedia($item)->toArray())->all(),
        ]);
    }

    public function viaMcp(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_via' => CreatedVia::Mcp,
        ]);
    }
}
