<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Media\Type as MediaType;
use App\Enums\Task\Priority;
use App\Enums\Task\Status;
use App\Enums\Task\TagColor;
use App\Models\Traits\HasMedia;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One of the Owner's to-dos (ADR 0016). Only the Mobile app reaches Tasks;
 * there is no web page or MCP tool for them. Its attachments are Media rows
 * on the Task, never workspace assets, so Posts cannot use them; deleting the
 * Task deletes their files.
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasMedia, HasUuids;

    /** The Media collection that holds the attachments. */
    public const string ATTACHMENTS_COLLECTION = 'attachments';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'color',
        'pinned',
        'due_at',
        'due_all_day',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'todo',
        'priority' => 0,
        'pinned' => false,
        'due_all_day' => false,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Task $task): void {
            $task->clearMediaCollection(self::ATTACHMENTS_COLLECTION);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'priority' => Priority::class,
            'color' => TagColor::class,
            'pinned' => 'boolean',
            'due_at' => 'datetime',
            'due_all_day' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Who created the Task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskTags(): BelongsToMany
    {
        return $this->belongsToMany(TaskTag::class);
    }

    /**
     * Oldest first, in the order they were added.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('collection', self::ATTACHMENTS_COLLECTION)
            ->oldest()
            ->oldest('id');
    }

    /**
     * The largest attachment of a type, in MB; 0 for a type a Task does not
     * take.
     */
    public static function attachmentMaxSizeInMb(MediaType $type): int
    {
        return (int) config("trypost.tasks.attachments.max_size_mb.{$type->value}", 0);
    }

    /**
     * @return list<MediaType>
     */
    protected function acceptedMediaTypes(): array
    {
        return [MediaType::Image, MediaType::Video, MediaType::Audio];
    }

    /**
     * Newest first.
     */
    public function timerSessions(): HasMany
    {
        return $this->hasMany(TimerSession::class)->latest('started_at');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->orderBy('remind_at');
    }

    /**
     * Tasks carrying every one of the given Tag groups' picks: at least one
     * of the picked Task tags in each group.
     *
     * @param  array<string, list<string>>  $taskTagIdsByGroup  picked Task tag ids, keyed by Tag group id
     */
    public function scopeWithTaskTagsFromEachGroup(Builder $query, array $taskTagIdsByGroup): void
    {
        foreach ($taskTagIdsByGroup as $taskTagIds) {
            $query->whereHas('taskTags', fn (Builder $taskTags) => $taskTags->whereKey($taskTagIds));
        }
    }

    /**
     * Tasks whose title or description contains the text, ignoring case.
     */
    public function scopeMatching(Builder $query, string $text): void
    {
        $query->where(fn (Builder $matching) => $matching
            ->whereLike('title', "%{$text}%")
            ->orWhereLike('description', "%{$text}%"));
    }
}
