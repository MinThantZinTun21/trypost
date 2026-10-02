<?php

declare(strict_types=1);

namespace App\Ai\Templates;

use App\Enums\PostPlatform\ContentType;

/**
 * The output of a template's assemble(): the caption to store as Post.content,
 * the media items to attach, and the content type the post platform should use.
 */
class GeneratedPost
{
    /**
     * @param  array<int, array<string, mixed>>  $media
     */
    public function __construct(
        public string $content,
        public array $media,
        public ?ContentType $contentType,
    ) {}
}
