<?php

declare(strict_types=1);

namespace App\Enums\ContentIdea;

enum Status: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => __('ideas.status.new'),
            self::InProgress => __('ideas.status.in_progress'),
            self::Done => __('ideas.status.done'),
        };
    }
}
