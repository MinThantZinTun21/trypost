<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Task\TagColor;
use Database\Factories\TagGroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, coloured set of Task tags, such as Context with Home and Office
 * (ADR 0016). Deleting it deletes its Task tags.
 */
class TagGroup extends Model
{
    /** @use HasFactory<TagGroupFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'color' => TagColor::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function taskTags(): HasMany
    {
        return $this->hasMany(TaskTag::class);
    }
}
