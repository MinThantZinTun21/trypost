<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\Task\ReminderType;
use App\Models\Reminder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

class SyncTaskReminders
{
    /**
     * Gives the Task exactly the given Reminders, or moves its own with the
     * due when none are given. A Reminder that was already there keeps its
     * id, so the phone's alarm for it stays the same, and keeps its dismissal
     * unless its time moved. A Reminder before the due goes when the due does.
     *
     * @param  list<array{type: ReminderType, remind_at: ?CarbonImmutable, minutes_before: ?int, repeat_until_dismissed: bool}>|null  $reminders
     */
    public static function execute(Task $task, ?array $reminders): void
    {
        $existing = $task->reminders()->get();
        $reminders ??= $existing->map(fn (Reminder $reminder): array => [
            'type' => $reminder->type,
            'remind_at' => $reminder->remind_at,
            'minutes_before' => $reminder->minutes_before,
            'repeat_until_dismissed' => $reminder->repeat_until_dismissed,
        ])->all();

        $wanted = collect($reminders)
            ->map(fn (array $reminder): ?array => self::resolve($task, $reminder))
            ->filter()
            ->keyBy(fn (array $reminder): string => self::key($reminder));

        /** @var Collection<string, Reminder> $kept */
        $kept = new Collection;
        $existing->each(function (Reminder $reminder) use ($kept): void {
            $key = self::key([
                'type' => $reminder->type,
                'remind_at' => $reminder->remind_at,
                'minutes_before' => $reminder->minutes_before,
            ]);

            if ($kept->has($key)) {
                $reminder->delete();
            } else {
                $kept->put($key, $reminder);
            }
        });

        $kept->reject(fn (Reminder $reminder, string $key): bool => $wanted->has($key))
            ->each(fn (Reminder $reminder) => $reminder->delete());

        $wanted->each(function (array $reminder, string $key) use ($task, $kept): void {
            $stored = $kept->get($key) ?? $task->reminders()->make();
            $hasMoved = $stored->exists && ! $stored->remind_at->equalTo(data_get($reminder, 'remind_at'));

            $stored->fill([
                'type' => data_get($reminder, 'type'),
                'remind_at' => data_get($reminder, 'remind_at'),
                'minutes_before' => data_get($reminder, 'minutes_before'),
                'repeat_until_dismissed' => data_get($reminder, 'repeat_until_dismissed'),
                'dismissed_at' => $hasMoved ? null : $stored->dismissed_at,
            ])->save();
        });
    }

    /**
     * The Reminder with its time worked out, or null for one before a due
     * the Task no longer has.
     *
     * @param  array{type: ReminderType, remind_at: ?CarbonImmutable, minutes_before: ?int, repeat_until_dismissed: bool}  $reminder
     * @return array{type: ReminderType, remind_at: CarbonImmutable, minutes_before: ?int, repeat_until_dismissed: bool}|null
     */
    private static function resolve(Task $task, array $reminder): ?array
    {
        if (data_get($reminder, 'type') === ReminderType::At) {
            return [...$reminder, 'minutes_before' => null];
        }

        if ($task->due_at === null) {
            return null;
        }

        return [...$reminder, 'remind_at' => $task->due_at->subMinutes((int) data_get($reminder, 'minutes_before'))];
    }

    /**
     * What makes two Reminders the same one: the fixed time, or the minutes
     * before the due.
     *
     * @param  array{type: ReminderType, remind_at: ?DateTimeInterface, minutes_before: ?int}  $reminder
     */
    private static function key(array $reminder): string
    {
        $remindAt = data_get($reminder, 'remind_at');
        $minutesBefore = data_get($reminder, 'minutes_before');

        return match (data_get($reminder, 'type')) {
            ReminderType::At => "at:{$remindAt->getTimestamp()}",
            ReminderType::BeforeDue => "before_due:{$minutesBefore}",
        };
    }
}
