<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskTagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A keyword the Owner puts on Tasks, in exactly one Tag group (ADR 0016).
 * Not a YouTube Short's Tags, and not the removed Labels.
 */
class TaskTag extends Model
{
    /** @use HasFactory<TaskTagFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'tag_group_id',
        'name',
    ];

    public function tagGroup(): BelongsTo
    {
        return $this->belongsTo(TagGroup::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
