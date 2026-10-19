<?php

declare(strict_types=1);

use App\Enums\Task\Status;
use App\Enums\Task\TimerOutcome;
use App\Models\Task;
use App\Models\TimerSession;
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
function timerTask(array $attributes = []): Task
{
    return Task::factory()->for(test()->workspace)->create($attributes);
}

test('a pomodoro starts on a to do task and moves it to in progress', function () {
    $task = timerTask(['title' => 'Edit the video']);

    $this->postJson(route('mobile.v1.timer.start', $task), ['mode' => 'pomodoro', 'planned_seconds' => 1500], $this->headers)
        ->assertCreated()
        ->assertJsonPath('task_id', $task->id)
        ->assertJsonPath('mode', 'pomodoro')
        ->assertJsonPath('started_at', '2030-01-01T09:00:00+00:00')
        ->assertJsonPath('ended_at', null)
        ->assertJsonPath('planned_seconds', 1500)
        ->assertJsonPath('outcome', null)
        ->assertJsonPath('elapsed_seconds', 0)
        ->assertJsonPath('task.title', 'Edit the video');

    expect($task->fresh()->status)->toBe(Status::InProgress)
        ->and(TimerSession::query()->sole()->workspace_id)->toBe($this->workspace->id);
});

test('a free timer has no planned length', function () {
    $this->postJson(route('mobile.v1.timer.start', timerTask()), ['mode' => 'free'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('planned_seconds', null);
});

test('the start is validated', function (array $body, string $error) {
    $this->postJson(route('mobile.v1.timer.start', timerTask()), $body, $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'no mode' => [[], 'mode'],
    'unknown mode' => [['mode' => 'lap'], 'mode'],
    'pomodoro without a length' => [['mode' => 'pomodoro'], 'planned_seconds'],
    'pomodoro under a minute' => [['mode' => 'pomodoro', 'planned_seconds' => 59], 'planned_seconds'],
    'pomodoro over four hours' => [['mode' => 'pomodoro', 'planned_seconds' => 14401], 'planned_seconds'],
    'free with a length' => [['mode' => 'free', 'planned_seconds' => 600], 'planned_seconds'],
]);

test('a second timer is refused while one runs, on any task', function () {
    TimerSession::factory()->for(timerTask())->create();

    $this->postJson(route('mobile.v1.timer.start', timerTask()), ['mode' => 'free'], $this->headers)
        ->assertConflict()
        ->assertJsonPath('message', __('tasks.timer_already_running'));

    expect(TimerSession::query()->count())->toBe(1);
});

test('another workspace\'s running timer does not block one here', function () {
    TimerSession::factory()->create();

    $this->postJson(route('mobile.v1.timer.start', timerTask()), ['mode' => 'free'], $this->headers)->assertCreated();
});

test('a pomodoro whose time ran out completes and lets a new timer start', function () {
    $finished = TimerSession::factory()->for(timerTask())->pomodoro(1500)->create(['started_at' => now()->subMinutes(30)]);

    $this->postJson(route('mobile.v1.timer.start', timerTask()), ['mode' => 'free'], $this->headers)->assertCreated();

    expect($finished->fresh()->outcome)->toBe(TimerOutcome::Completed)
        ->and($finished->fresh()->ended_at->toIso8601String())->toBe('2030-01-01T08:55:00+00:00');
});

test('a done task cannot start a timer', function () {
    $this->postJson(route('mobile.v1.timer.start', Task::factory()->for($this->workspace)->done()->create()), ['mode' => 'free'], $this->headers)
        ->assertConflict()
        ->assertJsonPath('message', __('tasks.timer_task_done'));
});

test('stop ends a free timer as completed and a short pomodoro as stopped', function () {
    $free = TimerSession::factory()->for(timerTask())->create();
    $short = TimerSession::factory()->for(timerTask())->pomodoro(1500)->create();

    $this->postJson(route('mobile.v1.timer.stop', $free), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('ended_at', '2030-01-01T09:00:00+00:00')
        ->assertJsonPath('outcome', 'completed')
        ->assertJsonPath('elapsed_seconds', 600);

    $this->postJson(route('mobile.v1.timer.stop', $short), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('outcome', 'stopped');
});

test('stopping a pomodoro after its time completes it at its planned end', function () {
    $session = TimerSession::factory()->for(timerTask())->pomodoro(300)->create();

    $this->postJson(route('mobile.v1.timer.stop', $session), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('ended_at', '2030-01-01T08:55:00+00:00')
        ->assertJsonPath('outcome', 'completed')
        ->assertJsonPath('elapsed_seconds', 300);
});

test('stopping an ended session changes nothing', function () {
    $session = TimerSession::factory()->for(timerTask())->ended(TimerOutcome::Stopped)->create();

    Carbon::setTestNow('2030-01-01 10:00:00');

    $this->postJson(route('mobile.v1.timer.stop', $session), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('ended_at', '2030-01-01T09:00:00+00:00')
        ->assertJsonPath('outcome', 'stopped');
});

test('marking the task done stops its running timer', function () {
    $task = timerTask(['status' => 'in_progress']);
    $session = TimerSession::factory()->for($task)->create();

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'done'], $this->headers)
        ->assertOk()
        ->assertJsonPath('timer_sessions.0.outcome', 'stopped');

    expect($session->fresh()->ended_at)->not->toBeNull();
});

test('the running timer is shown with its task, or null', function () {
    $this->getJson(route('mobile.v1.timer.show'), $this->headers)
        ->assertOk()
        ->assertExactJson(['data' => null]);

    $session = TimerSession::factory()->for(timerTask(['title' => 'Write']))->pomodoro()->create();

    $this->getJson(route('mobile.v1.timer.show'), $this->headers)
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.elapsed_seconds', 600)
        ->assertJsonPath('data.task.title', 'Write');

    Carbon::setTestNow('2030-01-01 10:00:00');

    $this->getJson(route('mobile.v1.timer.show'), $this->headers)->assertExactJson(['data' => null]);
    expect($session->fresh()->outcome)->toBe(TimerOutcome::Completed);
});

test('the task carries its tracked time and sessions, newest first', function () {
    $task = timerTask();
    $older = TimerSession::factory()->for($task)->create(['started_at' => now()->subHours(2), 'ended_at' => now()->subHours(2)->addMinutes(20), 'outcome' => 'completed']);
    $running = TimerSession::factory()->for($task)->create(['started_at' => now()->subMinutes(5)]);

    $this->getJson(route('mobile.v1.tasks.show', $task), $this->headers)
        ->assertOk()
        ->assertJsonPath('tracked_seconds', 1500)
        ->assertJsonPath('timer_sessions.*.id', [$running->id, $older->id]);
});

test('another workspace\'s timer cannot be started or stopped', function () {
    $foreignTask = Task::factory()->create();
    $session = TimerSession::factory()->for($foreignTask)->create();

    $this->postJson(route('mobile.v1.timer.start', $foreignTask), ['mode' => 'free'], $this->headers)->assertNotFound();
    $this->postJson(route('mobile.v1.timer.stop', $session), [], $this->headers)->assertNotFound();

    expect($session->fresh()->ended_at)->toBeNull();
});

test('deleting a task deletes its sessions', function () {
    $task = timerTask();
    TimerSession::factory()->for($task)->create();

    $this->deleteJson(route('mobile.v1.tasks.destroy', $task), [], $this->headers)->assertNoContent();

    expect(TimerSession::query()->count())->toBe(0);
});

test('the timer needs the mobile token', function () {
    $this->getJson(route('mobile.v1.timer.show'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.timer.start', timerTask()), ['mode' => 'free'])->assertUnauthorized();
});
