<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PostInsight;
use App\Models\PostPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostInsight>
 */
class PostInsightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_platform_id' => PostPlatform::factory()->facebook()->published(),
            'feed_post_id' => $this->faker->numerify('##########_##########'),
            'views' => $this->faker->numberBetween(0, 10000),
            'reach' => $this->faker->numberBetween(0, 5000),
            'reactions' => $this->faker->numberBetween(0, 500),
            'clicks' => $this->faker->numberBetween(0, 300),
            'read_at' => now(),
        ];
    }
}
