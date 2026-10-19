<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Task\TimerMode;
use App\Enums\Task\TimerOutcome;
use Carbon\CarbonImmutable;
use Database\Factories\TimerSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time the Owner spent on a Task with the timer (ADR 0016). The phone shows
 * the clock from `started_at`; the server keeps the record.
 */
class TimerSession extends Model
{
    /** @use HasFactory<TimerSessionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'task_id',
        'mode',
        'started_at',
        'ended_at',
        'planned_seconds',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'mode' => TimerMode::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'planned_seconds' => 'integer',
            'outcome' => TimerOutcome::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * When a Pomodoro's planned length runs out, or null for a free timer.
     */
    public function plannedEndAt(): ?CarbonImmutable
    {
        if ($this->mode !== TimerMode::Pomodoro || $this->planned_seconds === null) {
            return null;
        }

        return $this->started_at->addSeconds($this->planned_seconds);
    }

    /**
     * Seconds on the clock: up to the end, or to now while it runs, never past
     * a Pomodoro's planned length.
     */
    public function elapsedSeconds(): int
    {
        $end = $this->ended_at ?? now();
        $plannedEndAt = $this->plannedEndAt();
        if ($plannedEndAt !== null && $end->isAfter($plannedEndAt)) {
            $end = $plannedEndAt;
        }

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    /**
     * Ends a running Pomodoro whose time ran out while no one was watching,
     * as Completed at its planned end.
     */
    public function finishIfElapsed(): self
    {
        $plannedEndAt = $this->plannedEndAt();
        if ($this->isRunning() && $plannedEndAt !== null && ! $plannedEndAt->isAfter(now())) {
            $this->update(['ended_at' => $plannedEndAt, 'outcome' => TimerOutcome::Completed]);
        }

        return $this;
    }
}
