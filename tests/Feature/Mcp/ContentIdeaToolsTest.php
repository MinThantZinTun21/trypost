<?php

declare(strict_types=1);

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreateContentIdea;
use App\Mcp\Tools\GetContentIdea;
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
