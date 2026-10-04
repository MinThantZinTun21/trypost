<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
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
            'platform' => Platform::Facebook,
            'platform_user_id' => $this->faker->uuid(),
            'username' => $this->faker->userName(),
            'display_name' => $this->faker->name(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_expires_at' => now()->addDays(60),
            'scopes' => [],
            'meta' => [],
            'status' => Status::Connected,
        ];
    }

    public function tiktok(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::TikTok,
            'scopes' => Platform::TikTok->requiredPublishScopes(),
        ]);
    }

    public function youtube(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::YouTube,
            'scopes' => Platform::YouTube->requiredPublishScopes(),
        ]);
    }

    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => Platform::Facebook,
            'scopes' => Platform::Facebook->requiredPublishScopes(),
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Disconnected,
            'error_message' => 'Token expired',
            'disconnected_at' => now(),
        ]);
    }

    public function tokenExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::TokenExpired,
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
