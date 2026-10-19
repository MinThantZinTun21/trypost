<?php

declare(strict_types=1);

use App\Models\TagGroup;
use App\Models\Task;
use App\Models\TaskTag;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->headers = mobileHeaders($this->owner);
});

test('tag groups are listed by name with their task tags by name', function () {
    $context = TagGroup::factory()->for($this->workspace)->create(['name' => 'Context', 'color' => 'blue']);
    TaskTag::factory()->for($context)->create(['name' => 'Office']);
    TaskTag::factory()->for($context)->create(['name' => 'Home']);
    TagGroup::factory()->for($this->workspace)->create(['name' => 'Area', 'color' => 'green']);
    TagGroup::factory()->create(['name' => 'Foreign']);

    $this->getJson(route('mobile.v1.tag-groups.index'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Area', 'Context'])
        ->assertJsonPath('data.1.color', 'blue')
        ->assertJsonPath('data.1.task_tags.*.name', ['Home', 'Office'])
        ->assertJsonPath('data.1.task_tags.0.tag_group_id', $context->id);
});

test('a tag group is created', function () {
    $this->postJson(route('mobile.v1.tag-groups.store'), ['name' => 'Context', 'color' => 'teal'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('name', 'Context')
        ->assertJsonPath('color', 'teal')
        ->assertJsonPath('task_tags', []);

    expect(TagGroup::query()->sole()->workspace_id)->toBe($this->workspace->id);
});

test('a tag group is refused for invalid fields', function (array $body, string $field) {
    TagGroup::factory()->for($this->workspace)->create(['name' => 'Context']);

    $this->postJson(route('mobile.v1.tag-groups.store'), ['name' => 'Area', 'color' => 'blue', ...$body], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a long name' => [['name' => str_repeat('a', 101)], 'name'],
    'a taken name' => [['name' => 'Context'], 'name'],
    'a taken name in another case' => [['name' => 'CONTEXT'], 'name'],
    'an unknown colour' => [['color' => 'violet'], 'color'],
]);

test('a name with like wildcards is compared literally', function () {
    TagGroup::factory()->for($this->workspace)->create(['name' => 'Contexts']);

    $this->postJson(route('mobile.v1.tag-groups.store'), ['name' => 'Context_', 'color' => 'blue'], $this->headers)
        ->assertCreated();
});

test('another workspace may use the same tag group name', function () {
    TagGroup::factory()->create(['name' => 'Context']);

    $this->postJson(route('mobile.v1.tag-groups.store'), ['name' => 'Context', 'color' => 'blue'], $this->headers)
        ->assertCreated();
});

test('a tag group is renamed and recoloured, keeping its own name', function () {
    $tagGroup = TagGroup::factory()->for($this->workspace)->create(['name' => 'Context', 'color' => 'blue']);

    $this->putJson(route('mobile.v1.tag-groups.update', $tagGroup), ['name' => 'Context', 'color' => 'red'], $this->headers)
        ->assertOk()
        ->assertJsonPath('color', 'red');

    $this->putJson(route('mobile.v1.tag-groups.update', $tagGroup), ['name' => 'Where', 'color' => 'red'], $this->headers)
        ->assertOk()
        ->assertJsonPath('name', 'Where');
});

test('deleting a tag group deletes its task tags and takes them off tasks', function () {
    $tagGroup = TagGroup::factory()->for($this->workspace)->create();
    $taskTag = TaskTag::factory()->for($tagGroup)->create();
    $task = Task::factory()->for($this->workspace)->create();
    $task->taskTags()->attach($taskTag);

    $this->deleteJson(route('mobile.v1.tag-groups.destroy', $tagGroup), [], $this->headers)->assertNoContent();

    expect($tagGroup->fresh())->toBeNull()
        ->and($taskTag->fresh())->toBeNull()
        ->and($task->fresh())->not->toBeNull();
    $this->assertDatabaseMissing('task_task_tag', ['task_id' => $task->id]);
});

test('a task tag is created in a group', function () {
    $tagGroup = TagGroup::factory()->for($this->workspace)->create();

    $this->postJson(route('mobile.v1.task-tags.store', $tagGroup), ['name' => 'Office'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('name', 'Office')
        ->assertJsonPath('tag_group_id', $tagGroup->id);
});

test('a task tag name is unique within its group only', function () {
    $context = TagGroup::factory()->for($this->workspace)->create();
    $area = TagGroup::factory()->for($this->workspace)->create();
    TaskTag::factory()->for($context)->create(['name' => 'Office']);

    $this->postJson(route('mobile.v1.task-tags.store', $context), ['name' => 'office'], $this->headers)
        ->assertJsonValidationErrors('name');
    $this->postJson(route('mobile.v1.task-tags.store', $area), ['name' => 'Office'], $this->headers)
        ->assertCreated();
});

test('a task tag is renamed, keeping its own name', function () {
    $taskTag = TaskTag::factory()->for(TagGroup::factory()->for($this->workspace))->create(['name' => 'Office']);

    $this->putJson(route('mobile.v1.task-tags.update', $taskTag), ['name' => 'Office'], $this->headers)->assertOk();
    $this->putJson(route('mobile.v1.task-tags.update', $taskTag), ['name' => 'Work'], $this->headers)
        ->assertOk()
        ->assertJsonPath('name', 'Work');
});

test('deleting a task tag takes it off tasks', function () {
    $taskTag = TaskTag::factory()->for(TagGroup::factory()->for($this->workspace))->create();
    $task = Task::factory()->for($this->workspace)->create();
    $task->taskTags()->attach($taskTag);

    $this->deleteJson(route('mobile.v1.task-tags.destroy', $taskTag), [], $this->headers)->assertNoContent();

    expect($taskTag->fresh())->toBeNull();
    $this->assertDatabaseMissing('task_task_tag', ['task_id' => $task->id]);
});

test('another workspace\'s tag group and task tag are not found', function () {
    $tagGroup = TagGroup::factory()->create(['name' => 'Foreign']);
    $taskTag = TaskTag::factory()->for($tagGroup)->create(['name' => 'Theirs']);

    $this->putJson(route('mobile.v1.tag-groups.update', $tagGroup), ['name' => 'Mine', 'color' => 'blue'], $this->headers)->assertNotFound();
    $this->deleteJson(route('mobile.v1.tag-groups.destroy', $tagGroup), [], $this->headers)->assertNotFound();
    $this->postJson(route('mobile.v1.task-tags.store', $tagGroup), ['name' => 'Mine'], $this->headers)->assertNotFound();
    $this->putJson(route('mobile.v1.task-tags.update', $taskTag), ['name' => 'Mine'], $this->headers)->assertNotFound();
    $this->deleteJson(route('mobile.v1.task-tags.destroy', $taskTag), [], $this->headers)->assertNotFound();

    expect($tagGroup->fresh()->name)->toBe('Foreign')
        ->and($taskTag->fresh()->name)->toBe('Theirs');
});

test('tag groups need the token', function () {
    $this->getJson(route('mobile.v1.tag-groups.index'))->assertUnauthorized();
});
