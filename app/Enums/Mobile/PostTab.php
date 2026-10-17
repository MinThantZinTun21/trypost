<?php

declare(strict_types=1);

namespace App\Enums\Mobile;

use App\Enums\Post\Status as PostStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Mobile app's post list tabs (ADR 0010). A tab is not a Post status:
 * Scheduled also shows Posts going out now, and Failed also shows Partially
 * published ones, because both need the Owner's attention.
 */
enum PostTab: string
{
    case Scheduled = 'scheduled';
    case Draft = 'draft';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * @return list<PostStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Scheduled => [PostStatus::Publishing, PostStatus::Scheduled],
            self::Draft => [PostStatus::Draft],
            self::Published => [PostStatus::Published],
            self::Failed => [PostStatus::Failed, PostStatus::PartiallyPublished],
        };
    }

    /**
     * Limit the query to this tab's Posts in its order: Scheduled soonest
     * first, the others newest first.
     */
    public function apply(Builder $query): Builder
    {
        $query->whereIn('status', $this->statuses());

        return match ($this) {
            self::Scheduled => $query->oldest('scheduled_at')->oldest('created_at'),
            self::Published => $query->latest('published_at')->latest('created_at'),
            // PostgreSQL sorts nulls first on a descending order and MySQL last; undated posts go last on both.
            self::Draft, self::Failed => $query->orderByRaw('scheduled_at is null')
                ->latest('scheduled_at')
                ->latest('created_at'),
        };
    }
}
