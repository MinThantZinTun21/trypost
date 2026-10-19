<?php

declare(strict_types=1);

use App\Enums\Task\Priority;
use App\Enums\Task\Status;
use App\Models\TagGroup;
use App\Models\Task;
use App\Models\TaskTag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = mobileHeaders($this->owner);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function ownerTask(array $attributes = []): Task
{
    return Task::factory()->for(test()->workspace)->create($attributes);
}

function ownerTaskTag(string $group, string $name): TaskTag
{
    $tagGroup = TagGroup::query()->firstOrCreate(
        ['workspace_id' => test()->workspace->id, 'name' => $group],
        ['color' => 'blue'],
    );

    return TaskTag::factory()->for($tagGroup)->create(['name' => $name]);
}

/**
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function listedTaskTitles(array $query): array
{
    return test()->getJson(route('mobile.v1.tasks.index', $query), test()->headers)
        ->assertOk()
        ->json('data.*.title');
}

test('a task is created with its fields and task tags', function () {
    $office = ownerTaskTag('Context', 'Office');

    $response = $this->postJson(route('mobile.v1.tasks.store'), [
        'title' => 'Call the supplier',
        'description' => 'About the October invoice',
        'priority' => Priority::High->value,
        'due_at' => '2030-01-02T15:00:00+06:30',
        'task_tags' => [$office->id],
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('title', 'Call the supplier')
        ->assertJsonPath('description', 'About the October invoice')
        ->assertJsonPath('status', 'todo')
        ->assertJsonPath('priority', 3)
        ->assertJsonPath('due_at', '2030-01-02T08:30:00+00:00')
        ->assertJsonPath('due_all_day', false)
        ->assertJsonPath('completed_at', null)
        ->assertJsonPath('task_tags', [[
            'id' => $office->id,
            'name' => 'Office',
            'tag_group_id' => $office->tag_group_id,
            'tag_group_name' => 'Context',
            'color' => 'blue',
        ]]);

    $task = Task::query()->sole();
    expect($task->workspace_id)->toBe($this->workspace->id)
        ->and($task->user_id)->toBe($this->owner->id);
});

test('a task needs only a title', function () {
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Buy paper'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('priority', 0)
        ->assertJsonPath('due_at', null)
        ->assertJsonPath('due_all_day', false)
        ->assertJsonPath('task_tags', []);
});

test('an all-day task keeps the start of its day', function () {
    $this->postJson(route('mobile.v1.tasks.store'), [
        'title' => 'Pay rent',
        'due_at' => '2030-02-01T00:00:00+06:30',
        'due_all_day' => true,
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('due_at', '2030-01-31T17:30:00+00:00')
        ->assertJsonPath('due_all_day', true);
});

test('a task is refused for invalid fields', function (array $body, string $field) {
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Task', ...$body], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Task::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'a long title' => [['title' => str_repeat('a', 256)], 'title'],
    'an unknown priority' => [['priority' => 4], 'priority'],
    'a due without its offset' => [['due_at' => '2030-01-02T15:00:00'], 'due_at'],
    'a due past 2038' => [['due_at' => '2038-02-01T00:00:00Z'], 'due_at'],
    'all day without a due' => [['due_all_day' => true], 'due_all_day'],
    'an unknown task tag' => [['task_tags' => ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d']], 'task_tags.0'],
]);

test('the offset error says the time needs its offset', function () {
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Task', 'due_at' => '2030-01-02T15:00:00'], $this->headers)
        ->assertJsonValidationErrors(['due_at' => __('mobile.scheduled_at_offset')]);
});

test('another workspace\'s task tag cannot be put on a task', function () {
    $foreign = TaskTag::factory()->create();

    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Task', 'task_tags' => [$foreign->id]], $this->headers)
        ->assertJsonValidationErrors('task_tags.0');
});

test('a task is shown', function () {
    $task = ownerTask(['title' => 'Read contract']);

    $this->getJson(route('mobile.v1.tasks.show', $task), $this->headers)
        ->assertOk()
        ->assertJsonPath('id', $task->id)
        ->assertJsonPath('title', 'Read contract');
});

test('an update replaces every field and the task tags', function () {
    $home = ownerTaskTag('Context', 'Home');
    $office = ownerTaskTag('Context', 'Office');
    $task = ownerTask([
        'description' => 'old',
        'priority' => Priority::High,
        'due_at' => '2030-01-01 09:00:00',
    ]);
    $task->taskTags()->attach($home);

    $this->putJson(route('mobile.v1.tasks.update', $task), [
        'title' => 'Renamed',
        'task_tags' => [$office->id],
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('title', 'Renamed')
        ->assertJsonPath('description', null)
        ->assertJsonPath('priority', 0)
        ->assertJsonPath('due_at', null)
        ->assertJsonPath('task_tags.*.name', ['Office']);
});

test('an update without task tags removes them', function () {
    $task = ownerTask();
    $task->taskTags()->attach(ownerTaskTag('Context', 'Home'));

    $this->putJson(route('mobile.v1.tasks.update', $task), ['title' => 'Task'], $this->headers)
        ->assertOk()
        ->assertJsonPath('task_tags', []);
});

test('task tags are listed by group then name', function () {
    $task = ownerTask();
    $task->taskTags()->attach([
        ownerTaskTag('Priority', 'Urgent')->id,
        ownerTaskTag('Context', 'Office')->id,
        ownerTaskTag('Context', 'Home')->id,
    ]);

    $this->getJson(route('mobile.v1.tasks.show', $task), $this->headers)
        ->assertJsonPath('task_tags.*.name', ['Home', 'Office', 'Urgent']);
});

test('done stamps when the task was finished and reopening clears it', function () {
    Carbon::setTestNow('2030-01-01 10:00:00');
    $task = ownerTask();

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'done'], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'done')
        ->assertJsonPath('completed_at', '2030-01-01T10:00:00+00:00');

    Carbon::setTestNow('2030-01-01 11:00:00');
    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'done'], $this->headers)
        ->assertJsonPath('completed_at', '2030-01-01T10:00:00+00:00');

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'todo'], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'todo')
        ->assertJsonPath('completed_at', null);
});

test('a task can be moved to in progress', function () {
    $task = ownerTask();

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'in_progress'], $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
});

test('an unknown status is refused', function () {
    $task = ownerTask();

    $this->putJson(route('mobile.v1.tasks.status', $task), ['status' => 'archived'], $this->headers)
        ->assertJsonValidationErrors('status');

    expect($task->fresh()->status)->toBe(Status::Todo);
});

test('deleting a task removes it and keeps its task tags', function () {
    $office = ownerTaskTag('Context', 'Office');
    $task = ownerTask();
    $task->taskTags()->attach($office);

    $this->deleteJson(route('mobile.v1.tasks.destroy', $task), [], $this->headers)->assertNoContent();

    expect($task->fresh())->toBeNull()
        ->and($office->fresh())->not->toBeNull();
    $this->assertDatabaseMissing('task_task_tag', ['task_id' => $task->id]);
});

test('open tasks are listed by due with undated ones last', function () {
    ownerTask(['title' => 'Undated', 'created_at' => '2030-01-01 00:00:00']);
    ownerTask(['title' => 'Later', 'due_at' => '2030-01-03 09:00:00']);
    ownerTask(['title' => 'Sooner', 'due_at' => '2030-01-02 09:00:00', 'status' => Status::InProgress]);
    ownerTask(['title' => 'Finished', 'due_at' => '2030-01-01 09:00:00', 'status' => Status::Done, 'completed_at' => now()]);

    expect(listedTaskTitles(['tab' => 'open']))->toBe(['Sooner', 'Later', 'Undated']);
});

test('done tasks are listed newest finished first', function () {
    ownerTask(['title' => 'First', 'status' => Status::Done, 'completed_at' => '2030-01-01 09:00:00']);
    ownerTask(['title' => 'Second', 'status' => Status::Done, 'completed_at' => '2030-01-02 09:00:00']);
    ownerTask(['title' => 'Open']);

    expect(listedTaskTitles(['tab' => 'done']))->toBe(['Second', 'First']);
});

test('the list is paginated', function () {
    config(['app.pagination.default' => 2]);
    collect(range(1, 3))->each(fn (int $day) => ownerTask(['due_at' => "2030-01-0{$day} 09:00:00"]));

    $this->getJson(route('mobile.v1.tasks.index', ['tab' => 'open']), $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.last_page', 2);
});

test('task tags filter with or inside a group and and across groups', function () {
    $home = ownerTaskTag('Context', 'Home');
    $office = ownerTaskTag('Context', 'Office');
    $urgent = ownerTaskTag('Priority', 'Urgent');

    ownerTask(['title' => 'Home urgent', 'due_at' => '2030-01-01 09:00:00'])->taskTags()->attach([$home->id, $urgent->id]);
    ownerTask(['title' => 'Office urgent', 'due_at' => '2030-01-02 09:00:00'])->taskTags()->attach([$office->id, $urgent->id]);
    ownerTask(['title' => 'Office only', 'due_at' => '2030-01-03 09:00:00'])->taskTags()->attach($office);
    ownerTask(['title' => 'Untagged', 'due_at' => '2030-01-04 09:00:00']);

    expect(listedTaskTitles(['tab' => 'open', 'task_tags' => [$office->id]]))->toBe(['Office urgent', 'Office only'])
        ->and(listedTaskTitles(['tab' => 'open', 'task_tags' => [$home->id, $office->id]]))->toBe(['Home urgent', 'Office urgent', 'Office only'])
        ->and(listedTaskTitles(['tab' => 'open', 'task_tags' => [$home->id, $office->id, $urgent->id]]))->toBe(['Home urgent', 'Office urgent']);
});

test('the list is searched by title and description ignoring case', function () {
    ownerTask(['title' => 'Pay Ko Aung', 'description' => 'October INVOICE', 'due_at' => '2030-01-01 09:00:00']);
    ownerTask(['title' => 'Invoice the client', 'due_at' => '2030-01-02 09:00:00']);
    ownerTask(['title' => 'Buy paper', 'due_at' => '2030-01-03 09:00:00']);

    expect(listedTaskTitles(['tab' => 'open', 'q' => 'invoice']))->toBe(['Pay Ko Aung', 'Invoice the client']);
});

test('the list needs a known tab and the owner\'s task tags', function (array $query, string $field) {
    $this->getJson(route('mobile.v1.tasks.index', $query), $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no tab' => [[], 'tab'],
    'an unknown tab' => [['tab' => 'archived'], 'tab'],
    'an unknown task tag' => [['tab' => 'open', 'task_tags' => ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d']], 'task_tags.0'],
]);

test('another workspace\'s tasks are not listed', function () {
    Task::factory()->create(['title' => 'Foreign']);
    ownerTask(['title' => 'Mine']);

    expect(listedTaskTitles(['tab' => 'open']))->toBe(['Mine']);
});

test('another workspace\'s task is not found', function (string $method, string $route, array $body) {
    $task = Task::factory()->create(['title' => 'Foreign']);

    $this->json($method, route($route, $task), $body, $this->headers)->assertNotFound();

    expect($task->fresh()?->title)->toBe('Foreign');
})->with([
    'show' => ['GET', 'mobile.v1.tasks.show', []],
    'update' => ['PUT', 'mobile.v1.tasks.update', ['title' => 'Mine now']],
    'status' => ['PUT', 'mobile.v1.tasks.status', ['status' => 'done']],
    'delete' => ['DELETE', 'mobile.v1.tasks.destroy', []],
]);

test('tasks need the token', function () {
    $this->getJson(route('mobile.v1.tasks.index', ['tab' => 'open']))->assertUnauthorized();
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Task'])->assertUnauthorized();

    expect(Task::query()->count())->toBe(0);
});
