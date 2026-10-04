<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostPlatform>
 */
class PostPlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'social_account_id' => SocialAccount::factory(),
            'enabled' => true,
            'platform' => Platform::Facebook,
            'content_type' => ContentType::FacebookPost,
            'status' => Status::Pending,
            'meta' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Published,
            'platform_post_id' => $this->faker->uuid(),
            'platform_url' => $this->faker->url(),
            'published_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Failed,
            'error_message' => 'Failed to publish',
        ]);
    }

    public function tiktok(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::TikTok,
            'content_type' => ContentType::TikTokVideo,
            'meta' => ['privacy_level' => PrivacyLevel::SelfOnly->value],
        ]);
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::YouTube,
            'content_type' => ContentType::YouTubeShort,
        ]);
    }

    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Facebook,
            'content_type' => ContentType::FacebookPost,
        ]);
    }

    public function facebookReel(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Facebook,
            'content_type' => ContentType::FacebookReel,
        ]);
    }

    public function facebookStory(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Facebook,
            'content_type' => ContentType::FacebookStory,
        ]);
    }
}
