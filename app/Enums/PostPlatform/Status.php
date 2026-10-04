<?php

declare(strict_types=1);

namespace App\Enums\PostPlatform;

enum Status: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Retrying = 'retrying';
    case Published = 'published';
    case Failed = 'failed';

    /** Published or failed — counts toward settling the parent post. */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Published, self::Failed => true,
            default => false,
        };
    }

    /** The publish job must not run again. */
    public function isClosed(): bool
    {
        return $this->isFinished();
    }
}
