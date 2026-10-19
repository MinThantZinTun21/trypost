<?php

declare(strict_types=1);

use App\Enums\Task\ReminderType;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2030-01-01 09:00:00');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = mobileHeaders($this->owner);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function reminderTask(array $attributes = []): Task
{
    return Task::factory()->for(test()->workspace)->create($attributes);
}

test('a task is created with a reminder at a time and one before its due', function () {
    $response = $this->postJson(route('mobile.v1.tasks.store'), [
        'title' => 'Call the supplier',
        'due_at' => '2030-01-02T15:00:00+06:30',
        'reminders' => [
            ['type' => 'at', 'remind_at' => '2030-01-02T10:00:00+06:30', 'repeat_until_dismissed' => true],
            ['type' => 'before_due', 'minutes_before' => 30],
        ],
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonCount(2, 'reminders')
        ->assertJsonPath('reminders.0.type', 'at')
        ->assertJsonPath('reminders.0.remind_at', '2030-01-02T03:30:00+00:00')
        ->assertJsonPath('reminders.0.minutes_before', null)
        ->assertJsonPath('reminders.0.repeat_until_dismissed', true)
        ->assertJsonPath('reminders.0.dismissed_at', null)
        ->assertJsonPath('reminders.1.type', 'before_due')
        ->assertJsonPath('reminders.1.remind_at', '2030-01-02T08:00:00+00:00')
        ->assertJsonPath('reminders.1.minutes_before', 30)
        ->assertJsonPath('reminders.1.repeat_until_dismissed', false)
        ->assertJsonMissingPath('reminders.0.task');
});

test('reminders sent with an update replace the set, keeping the ones that stay', function () {
    $task = reminderTask(['due_at' => '2030-01-02 12:00:00']);
    $kept = Reminder::factory()->for($task)->dismissed()->create(['type' => ReminderType::BeforeDue, 'minutes_before' => 10, 'remind_at' => '2030-01-02 11:50:00']);
    $dropped = Reminder::factory()->for($task)->create(['remind_at' => '2030-01-02 08:00:00']);

    $this->putJson(route('mobile.v1.tasks.update', $task), [
        'title' => $task->title,
        'due_at' => '2030-01-02T12:00:00Z',
        'reminders' => [
            ['type' => 'before_due', 'minutes_before' => 10, 'repeat_until_dismissed' => true],
            ['type' => 'at', 'remind_at' => '2030-01-02T07:00:00Z'],
        ],
    ], $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'reminders')
        ->assertJsonPath('reminders.0.remind_at', '2030-01-02T07:00:00+00:00')
        ->assertJsonPath('reminders.1.id', $kept->id)
        ->assertJsonPath('reminders.1.repeat_until_dismissed', true)
        ->assertJsonPath('reminders.1.dismissed_at', $kept->dismissed_at->toIso8601String());

    expect(Reminder::query()->find($dropped->id))->toBeNull();
});

test('an update without reminders keeps them and moves the ones before the due', function () {
    $task = reminderTask(['due_at' => '2030-01-02 12:00:00']);
    $before = Reminder::factory()->for($task)->dismissed()->create(['type' => ReminderType::BeforeDue, 'minutes_before' => 60, 'remind_at' => '2030-01-02 11:00:00']);
    $at = Reminder::factory()->for($task)->dismissed()->create(['remind_at' => '2030-01-02 08:00:00']);

    $this->putJson(route('mobile.v1.tasks.update', $task), [
        'title' => $task->title,
        'due_at' => '2030-01-03T12:00:00Z',
    ], $this->headers)->assertOk();

    expect($before->fresh()->remind_at->toIso8601String())->toBe('2030-01-03T11:00:00+00:00')
        ->and($before->fresh()->dismissed_at)->toBeNull()
        ->and($at->fresh()->remind_at->toIso8601String())->toBe('2030-01-02T08:00:00+00:00')
        ->and($at->fresh()->dismissed_at)->not->toBeNull();
});

test('clearing the due drops the reminders before it', function () {
    $task = reminderTask(['due_at' => '2030-01-02 12:00:00']);
    Reminder::factory()->for($task)->create(['type' => ReminderType::BeforeDue, 'minutes_before' => 60, 'remind_at' => '2030-01-02 11:00:00']);
    $at = Reminder::factory()->for($task)->create();

    $this->putJson(route('mobile.v1.tasks.update', $task), ['title' => $task->title], $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'reminders')
        ->assertJsonPath('reminders.0.id', $at->id);
});

test('an empty list removes every reminder', function () {
    $task = reminderTask();
    Reminder::factory()->for($task)->count(2)->create();

    $this->putJson(route('mobile.v1.tasks.update', $task), ['title' => $task->title, 'reminders' => []], $this->headers)
        ->assertOk()
        ->assertJsonCount(0, 'reminders');
});

test('reminders are validated', function (array $reminders, string $error) {
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Task', 'reminders' => $reminders], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'unknown type' => [[['type' => 'daily']], 'reminders.0.type'],
    'at without a time' => [[['type' => 'at']], 'reminders.0.remind_at'],
    'a time without its offset' => [[['type' => 'at', 'remind_at' => '2030-01-02T10:00:00']], 'reminders.0.remind_at'],
    'a time past 2038' => [[['type' => 'at', 'remind_at' => '2038-02-01T10:00:00Z']], 'reminders.0.remind_at'],
    'before the due without minutes' => [[['type' => 'before_due']], 'reminders.0.minutes_before'],
    'before the due with no due' => [[['type' => 'before_due', 'minutes_before' => 10]], 'reminders.0.minutes_before'],
    'more than four weeks before' => [[['type' => 'before_due', 'minutes_before' => 40321]], 'reminders.0.minutes_before'],
    'more than ten' => [array_fill(0, 11, ['type' => 'at', 'remind_at' => '2030-01-02T10:00:00Z']), 'reminders'],
]);

test('upcoming lists undismissed reminders of open tasks, soonest first, with their task', function () {
    $open = reminderTask(['title' => 'Open', 'due_at' => '2030-01-03 12:00:00']);
    $later = Reminder::factory()->for($open)->create(['remind_at' => '2030-01-03 08:00:00']);
    $past = Reminder::factory()->for($open)->create(['remind_at' => '2029-12-31 08:00:00', 'repeat_until_dismissed' => true]);
    Reminder::factory()->for($open)->dismissed()->create();
    Reminder::factory()->for(Task::factory()->for($this->workspace)->done()->create())->create();
    Reminder::factory()->for(Task::factory()->create())->create();

    $this->getJson(route('mobile.v1.reminders.upcoming'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.*.id', [$past->id, $later->id])
        ->assertJsonPath('data.0.task', [
            'id' => $open->id,
            'title' => 'Open',
            'description' => null,
            'due_at' => '2030-01-03T12:00:00+00:00',
            'due_all_day' => false,
        ]);
});

test('an in progress task\'s reminders are upcoming', function () {
    $reminder = Reminder::factory()->for(reminderTask(['status' => 'in_progress']))->create();

    $this->getJson(route('mobile.v1.reminders.upcoming'), $this->headers)
        ->assertJsonPath('data.*.id', [$reminder->id]);
});

test('dismiss stops a reminder and keeps the first dismissal', function () {
    $reminder = Reminder::factory()->for(reminderTask())->create();

    $this->postJson(route('mobile.v1.reminders.dismiss', $reminder), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('id', $reminder->id)
        ->assertJsonPath('dismissed_at', '2030-01-01T09:00:00+00:00');

    Carbon::setTestNow('2030-01-01 10:00:00');

    $this->postJson(route('mobile.v1.reminders.dismiss', $reminder), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('dismissed_at', '2030-01-01T09:00:00+00:00');

    $this->getJson(route('mobile.v1.reminders.upcoming'), $this->headers)->assertJsonCount(0, 'data');
});

test('done dismisses the reminders that already rang, so reopening keeps them quiet', function () {
    $task = reminderTask();
    $rang = Reminder::factory()->for($task)->create(['remind_at' => '2030-01-01 08:00:00']);
    $ahead = Reminder::factory()->for($task)->create(['remind_at' => '2030-01-02 08:00:00']);

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'done'], $this->headers)->assertOk();
    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'todo'], $this->headers)->assertOk();

    expect($rang->fresh()->dismissed_at?->toIso8601String())->toBe('2030-01-01T09:00:00+00:00')
        ->and($ahead->fresh()->dismissed_at)->toBeNull();
    $this->getJson(route('mobile.v1.reminders.upcoming'), $this->headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ahead->id);
});

test('another workspace\'s reminder cannot be dismissed', function () {
    $reminder = Reminder::factory()->for(Task::factory()->create())->create();

    $this->postJson(route('mobile.v1.reminders.dismiss', $reminder), [], $this->headers)->assertNotFound();

    expect($reminder->fresh()->dismissed_at)->toBeNull();
});

test('deleting a task deletes its reminders', function () {
    $task = reminderTask();
    Reminder::factory()->for($task)->create();

    $this->deleteJson(route('mobile.v1.tasks.destroy', $task), [], $this->headers)->assertNoContent();

    expect(Reminder::query()->count())->toBe(0);
});

test('reminders need the mobile token', function () {
    $reminder = Reminder::factory()->for(reminderTask())->create();

    $this->getJson(route('mobile.v1.reminders.upcoming'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.reminders.dismiss', $reminder))->assertUnauthorized();
});
