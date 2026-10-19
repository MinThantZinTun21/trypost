<?php

declare(strict_types=1);

namespace App\Enums\Mobile;

use App\Enums\Task\Status;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Mobile app's Task list tabs (ADR 0016): Open holds To do and In
 * progress, Done the finished Tasks.
 */
enum TaskTab: string
{
    case Open = 'open';
    case Done = 'done';

    /**
     * Limit the query to this tab's Tasks in its order: Open by due date with
     * undated Tasks last, Done newest first.
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            // PostgreSQL sorts nulls last on an ascending order and MySQL first; undated Tasks go last on both.
            self::Open => $query->whereIn('status', Status::open())
                ->orderByRaw('due_at is null')
                ->oldest('due_at')
                ->oldest('created_at')
                ->oldest('id'),
            self::Done => $query->where('status', Status::Done)
                ->latest('completed_at')
                ->latest('id'),
        };
    }
}
