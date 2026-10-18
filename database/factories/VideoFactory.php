<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\Video;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => $this->faker->sentence(3),
        ];
    }

    /**
     * A Video with its file attached.
     */
    public function withFile(?int $size = null, ?float $duration = null): static
    {
        return $this->afterCreating(function (Video $video) use ($size, $duration): void {
            Media::factory()->video()->for($video, 'mediable')->create([
                'collection' => Video::FILE_COLLECTION,
                ...($size === null ? [] : ['size' => $size]),
                ...($duration === null ? [] : ['meta' => ['duration' => $duration]]),
            ]);
        });
    }
}
