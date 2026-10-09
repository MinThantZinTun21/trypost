<?php

declare(strict_types=1);

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreateContentIdea;
use App\Mcp\Tools\GetContentIdea;
use App\Mcp\Tools\ListContentIdeas;
use App\Mcp\Tools\UpdateContentIdeaStatus;
use App\Models\ContentIdea;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

function ownerContentIdea(array $attributes = []): ContentIdea
{
    return ContentIdea::factory()->create([
        'workspace_id' => test()->workspace->id,
        ...$attributes,
    ]);
}

// create_content_idea
test('create_content_idea saves a new idea from the assistant', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(CreateContentIdea::class, [
            'title' => 'Three desk setups under $100',
            'details' => "## Hook\n\nStart on the cheapest one.",
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('content_idea.title', 'Three desk setups under $100')
            ->where('content_idea.details', "## Hook\n\nStart on the cheapest one.")
            ->where('content_idea.status', 'new')
            ->where('content_idea.created_via', 'mcp')
            ->whereType('content_idea.id', 'string')
            ->whereType('content_idea.created_at', 'string')
            ->whereType('content_idea.updated_at', 'string')
            ->where('content_idea.url', route('app.ideas.show', ContentIdea::query()->sole())));

    $idea = ContentIdea::query()->sole();

    expect($idea->workspace_id)->toBe($this->workspace->id)
        ->and($idea->status)->toBe(Status::New)
        ->and($idea->created_via)->toBe(CreatedVia::Mcp);
});

test('create_content_idea works without details', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(CreateContentIdea::class, ['title' => 'Quick one'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('content_idea.details', null)
            ->etc());
});

test('create_content_idea validates with the shared rules', function (array $arguments) {
    SchedulerServer::actingAs($this->owner)
        ->tool(CreateContentIdea::class, $arguments)
        ->assertHasErrors();

    expect(ContentIdea::query()->count())->toBe(0);
})->with([
    'missing title' => [['details' => 'No title']],
    'title too long' => [['title' => str_repeat('a', 256)]],
    'details too long' => [['title' => 'Fine', 'details' => str_repeat('a', 20001)]],
]);

// get_content_idea
test('get_content_idea returns the idea in full', function () {
    $idea = ownerContentIdea(['title' => 'Reel idea', 'details' => '- one', 'status' => Status::Done]);

    SchedulerServer::actingAs($this->owner)
        ->tool(GetContentIdea::class, ['id' => $idea->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('content_idea.id', $idea->id)
            ->where('content_idea.title', 'Reel idea')
            ->where('content_idea.details', '- one')
            ->where('content_idea.status', 'done')
            ->where('content_idea.created_via', 'web')
            ->where('content_idea.created_at', $idea->created_at->toIso8601String())
            ->where('content_idea.updated_at', $idea->updated_at->toIso8601String())
            ->where('content_idea.url', route('app.ideas.show', $idea)));
});

test('get_content_idea does not find an idea outside the owner\'s workspace', function (Closure $id) {
    SchedulerServer::actingAs($this->owner)
        ->tool(GetContentIdea::class, ['id' => $id()])
        ->assertHasErrors();
})->with([
    'other workspace' => [fn () => ContentIdea::factory()->create()->id],
    'unknown' => [fn () => fake()->uuid()],
    'not a uuid' => [fn () => '42'],
]);

// list_content_ideas
function contentIdeaTitles(AssertableJson $json): array
{
    return array_column($json->toArray()['content_ideas'], 'title');
}

test('list_content_ideas returns the latest 3, newest first, done included', function () {
    foreach (['Oldest', 'Older', 'Middle', 'Newer', 'Newest'] as $minutesAgo => $title) {
        ownerContentIdea(['title' => $title, 'created_at' => now()->subMinutes(10 - $minutesAgo)]);
    }
    ContentIdea::query()->where('title', 'Newest')->update(['status' => Status::Done]);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, [])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            expect(contentIdeaTitles($json))->toBe(['Newest', 'Newer', 'Middle'])
                ->and($json->toArray()['content_ideas'][0])->toMatchArray(['status' => 'done', 'details' => ContentIdea::query()->where('title', 'Newest')->value('details')])
                ->and(array_keys($json->toArray()['content_ideas'][0]))->toEqualCanonicalizing(['id', 'title', 'details', 'status', 'created_via', 'created_at', 'updated_at', 'url']);
            $json->etc();
        });
});

test('list_content_ideas returns count ideas', function (int $count, int $expected) {
    foreach (range(1, 4) as $minutesAgo) {
        ownerContentIdea(['created_at' => now()->subMinutes($minutesAgo)]);
    }

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['count' => $count])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('content_ideas', $expected));
})->with([
    'one' => [1, 1],
    'more than exist' => [50, 4],
]);

test('list_content_ideas treats a null count as the default 3', function () {
    foreach (range(1, 4) as $minutesAgo) {
        ownerContentIdea(['created_at' => now()->subMinutes($minutesAgo)]);
    }

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['count' => null])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('content_ideas', 3));
});

test('list_content_ideas breaks a same-second tie by creation order', function () {
    $this->freezeSecond();
    ownerContentIdea(['title' => 'First']);
    ownerContentIdea(['title' => 'Second']);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['count' => 1])
        ->assertStructuredContent(function (AssertableJson $json) {
            expect(contentIdeaTitles($json))->toBe(['Second']);
            $json->etc();
        });
});

test('list_content_ideas rejects a count outside 1 to 50', function (mixed $count) {
    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['count' => $count])
        ->assertHasErrors();
})->with([0, 51, -1, 'three']);

test('list_content_ideas filters by status', function (string $status, array $expected) {
    ownerContentIdea(['title' => 'Fresh', 'created_at' => now()->subMinutes(3)]);
    ownerContentIdea(['title' => 'Working', 'status' => Status::InProgress, 'created_at' => now()->subMinutes(2)]);
    ownerContentIdea(['title' => 'Shipped', 'status' => Status::Done, 'created_at' => now()->subMinute()]);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['status' => $status])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($expected) {
            expect(contentIdeaTitles($json))->toBe($expected);
            $json->etc();
        });
})->with([
    'new' => ['new', ['Fresh']],
    'in progress' => ['in_progress', ['Working']],
    'done' => ['done', ['Shipped']],
]);

test('list_content_ideas rejects an unknown status', function () {
    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, ['status' => 'archived'])
        ->assertHasErrors();
});

test('list_content_ideas only lists the owner\'s workspace', function () {
    ContentIdea::factory()->create(['title' => 'Someone else']);
    ownerContentIdea(['title' => 'Mine']);

    SchedulerServer::actingAs($this->owner)
        ->tool(ListContentIdeas::class, [])
        ->assertStructuredContent(function (AssertableJson $json) {
            expect(contentIdeaTitles($json))->toBe(['Mine']);
            $json->etc();
        });
});

// update_content_idea_status
test('update_content_idea_status moves the idea and returns it', function (string $status) {
    $idea = ownerContentIdea(['title' => 'Working on it']);

    SchedulerServer::actingAs($this->owner)
        ->tool(UpdateContentIdeaStatus::class, ['id' => $idea->id, 'status' => $status])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('content_idea.id', $idea->id)
            ->where('content_idea.title', 'Working on it')
            ->where('content_idea.status', $status)
            ->etc());

    expect($idea->refresh()->status)->toBe(Status::from($status));
})->with(['in_progress', 'done', 'new']);

test('a status the assistant sets shows on the web page', function () {
    $idea = ownerContentIdea();

    SchedulerServer::actingAs($this->owner)
        ->tool(UpdateContentIdeaStatus::class, ['id' => $idea->id, 'status' => 'done']);

    $this->actingAs($this->owner)
        ->get(route('app.ideas.index', 'done'))
        ->assertInertia(fn ($page) => $page->has('ideas.data', 1)->where('ideas.data.0.status', 'done'));
});

test('update_content_idea_status rejects an invalid status', function (mixed $status) {
    $idea = ownerContentIdea();

    SchedulerServer::actingAs($this->owner)
        ->tool(UpdateContentIdeaStatus::class, ['id' => $idea->id, 'status' => $status])
        ->assertHasErrors();

    expect($idea->refresh()->status)->toBe(Status::New);
})->with(['archived', '', null]);

test('update_content_idea_status does not find an idea outside the owner\'s workspace', function (Closure $id) {
    SchedulerServer::actingAs($this->owner)
        ->tool(UpdateContentIdeaStatus::class, ['id' => $id(), 'status' => 'done'])
        ->assertHasErrors();

    expect(ContentIdea::query()->where('status', Status::Done)->count())->toBe(0);
})->with([
    'other workspace' => [fn () => ContentIdea::factory()->create()->id],
    'unknown' => [fn () => fake()->uuid()],
    'not a uuid' => [fn () => '42'],
]);
