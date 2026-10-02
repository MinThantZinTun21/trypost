<?php

declare(strict_types=1);

namespace App\Ai\Templates;

use App\Enums\Ai\ContentStyle;
use InvalidArgumentException;

class AiTemplateRegistry
{
    /** @var array<int, class-string<AiContentTemplate>> */
    private const TEMPLATES = [
        ImageCardTemplate::class,
        TweetCardTemplate::class,
        TweetCardImageTemplate::class,
    ];

    /** @var array<int, AiContentTemplate>|null */
    private ?array $templates = null;

    /** @return array<int, AiContentTemplate> */
    public function all(): array
    {
        return $this->templates ??= array_map(fn (string $class) => app($class), self::TEMPLATES);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(fn (AiContentTemplate $t) => $t->key(), $this->all());
    }

    /** @return array<int, ContentStyle> */
    public function styles(): array
    {
        return ContentStyle::cases();
    }

    public function find(ContentStyle|string $style): AiContentTemplate
    {
        $value = $style instanceof ContentStyle ? $style->value : $style;

        foreach ($this->all() as $template) {
            if ($template->key() === $value) {
                return $template;
            }
        }

        throw new InvalidArgumentException("Unknown AI content template: {$value}");
    }

    public function default(): AiContentTemplate
    {
        return app(ImageCardTemplate::class);
    }
}
