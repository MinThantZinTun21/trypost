<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LiveStream\Privacy;
use App\Enums\LiveStream\Status;
use App\Models\LiveStream;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStream>
 */
class LiveStreamFactory extends Factory
{
    protected $model = LiveStream::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'privacy' => Privacy::Public,
            'status' => Status::Scheduled,
            'starts_at' => now()->addDay(),
            'youtube_broadcast_id' => $this->faker->regexify('[A-Za-z0-9]{11}'),
            'youtube_stream_id' => $this->faker->regexify('[A-Za-z0-9]{20}'),
            'ingest_url' => 'rtmp://a.rtmp.youtube.com/live2/'.$this->faker->uuid(),
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Status::Live,
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
        ]);
    }
}
