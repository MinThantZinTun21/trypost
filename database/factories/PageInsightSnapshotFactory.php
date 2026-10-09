<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PageInsightSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageInsightSnapshot>
 */
class PageInsightSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory()->facebook(),
            'date' => now()->subDay()->toDateString(),
            'followers' => $this->faker->numberBetween(100, 5000),
            'new_follows' => $this->faker->numberBetween(0, 50),
            'unfollows' => $this->faker->numberBetween(0, 10),
            'views' => $this->faker->numberBetween(0, 10000),
            'reach' => $this->faker->numberBetween(0, 5000),
            'engagements' => $this->faker->numberBetween(0, 500),
            'video_views' => $this->faker->numberBetween(0, 2000),
        ];
    }
}
