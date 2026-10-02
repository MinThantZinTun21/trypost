<?php

declare(strict_types=1);

namespace App\Ai\Templates\Concerns;

use App\Enums\PostPlatform\ContentType;

trait ResolvesContentType
{
    private static function resolveContentType(string $format): ?ContentType
    {
        if ($format === ContentType::CAROUSEL_FORMAT) {
            return ContentType::InstagramFeed;
        }

        return ContentType::tryFrom($format);
    }
}
