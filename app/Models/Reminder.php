<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Task\ReminderType;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time the Owner's phone rings about a Task (ADR 0016). The phone sets the
 * alarms; the server only keeps the list and who dismissed what.
 */
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'task_id',
        'type',
        'remind_at',
        'minutes_before',
        'repeat_until_dismissed',
        'dismissed_at',
    ];

    protected $attributes = [
        'repeat_until_dismissed' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'remind_at' => 'datetime',
            'minutes_before' => 'integer',
            'repeat_until_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
