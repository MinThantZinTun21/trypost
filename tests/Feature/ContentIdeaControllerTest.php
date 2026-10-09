<?php

declare(strict_types=1);

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use App\Enums\UserWorkspace\Role;
use App\Models\ContentIdea;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index
test('ideas index requires authentication', function () {
    $this->get(route('app.ideas.index'))->assertRedirect(route('login'));
});

test('ideas index lists the workspace ideas newest first', function () {
    $older = ContentIdea::factory()->create(['workspace_id' => $this->workspace->id, 'created_at' => now()->subDay()]);
    $newer = ContentIdea::factory()->viaMcp()->create(['workspace_id' => $this->workspace->id, 'details' => "# Hook\n\nOpen with a **question**."]);

    $this->actingAs($this->user)
        ->get(route('app.ideas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ideas/Index')
            ->has('ideas.data', 2)
            ->where('ideas.data.0.id', $newer->id)
            ->where('ideas.data.0.excerpt', 'Hook Open with a question.')
            ->where('ideas.data.0.created_via', CreatedVia::Mcp->value)
            ->where('ideas.data.0.status', Status::New->value)
            ->where('ideas.data.1.id', $older->id)
            ->where('currentStatus', null)
        );
});

test('ideas index filters by status and still lists done ideas', function (string $status, int $expected) {
    ContentIdea::factory()->create(['workspace_id' => $this->workspace->id]);
    ContentIdea::factory()->inProgress()->create(['workspace_id' => $this->workspace->id]);
    ContentIdea::factory()->done()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.ideas.index', $status))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ideas.data', $expected)
            ->where('currentStatus', $status)
        );
})->with([
    'new' => ['new', 1],
    'in progress' => ['in_progress', 1],
    'done' => ['done', 2],
]);

test('ideas index rejects an unknown status filter', function () {
    $this->actingAs($this->user)
        ->get(route('app.ideas.index').'/archived')
        ->assertNotFound();
});

test('ideas index paginates with the configured page size', function () {
    config(['app.pagination.default' => 2]);
    ContentIdea::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.ideas.index'))
        ->assertInertia(fn ($page) => $page->has('ideas.data', 2));
});

test('ideas index never lists another workspace ideas', function () {
    ContentIdea::factory()->create();

    $this->actingAs($this->user)
        ->get(route('app.ideas.index'))
        ->assertInertia(fn ($page) => $page->has('ideas.data', 0));
});

// Store
test('storing an idea saves it as new from the web', function () {
    $this->actingAs($this->user)
        ->from(route('app.ideas.index'))
        ->post(route('app.ideas.store'), [
            'title' => 'Behind the scenes reel',
            'details' => "- Show the desk\n- End on the logo",
        ])
        ->assertRedirect(route('app.ideas.index'));

    $idea = ContentIdea::query()->sole();

    expect($idea->workspace_id)->toBe($this->workspace->id)
        ->and($idea->title)->toBe('Behind the scenes reel')
        ->and($idea->details)->toBe("- Show the desk\n- End on the logo")
        ->and($idea->status)->toBe(Status::New)
        ->and($idea->created_via)->toBe(CreatedVia::Web);
});

test('storing an idea without details is allowed', function () {
    $this->actingAs($this->user)
        ->post(route('app.ideas.store'), ['title' => 'Quick one'])
        ->assertSessionHasNoErrors();

    expect(ContentIdea::query()->sole()->details)->toBeNull();
});

test('storing an idea ignores a submitted status', function () {
    $this->actingAs($this->user)
        ->post(route('app.ideas.store'), ['title' => 'Quick one', 'status' => 'done'])
        ->assertSessionHasNoErrors();

    expect(ContentIdea::query()->sole()->status)->toBe(Status::New);
});

test('storing an idea validates the title and details', function (array $payload, string $field) {
    $this->actingAs($this->user)
        ->post(route('app.ideas.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(ContentIdea::query()->count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'title too long' => [['title' => str_repeat('a', 256)], 'title'],
    'details too long' => [['title' => 'Fine', 'details' => str_repeat('a', 20001)], 'details'],
]);

test('storing an idea requires authentication', function () {
    $this->post(route('app.ideas.store'), ['title' => 'Nope'])->assertRedirect(route('login'));

    expect(ContentIdea::query()->count())->toBe(0);
});

// Policy
test('the policy hides another workspace idea as not found', function (string $ability) {
    $foreign = ContentIdea::factory()->create();

    expect(Gate::forUser($this->user)->inspect($ability, $foreign)->status())->toBe(404);
})->with(['view', 'update', 'delete']);

test('the policy allows the owner on their own idea', function (string $ability) {
    $idea = ContentIdea::factory()->create(['workspace_id' => $this->workspace->id]);

    expect(Gate::forUser($this->user)->allows($ability, $idea))->toBeTrue();
})->with(['view', 'update', 'delete']);

// Show
test('the show page renders the details from markdown', function () {
    $idea = ContentIdea::factory()->create([
        'workspace_id' => $this->workspace->id,
        'details' => "## Hook\n\n- One\n- Two\n\n`code` and [a link](https://example.com)",
    ]);

    $this->actingAs($this->user)
        ->get(route('app.ideas.show', $idea))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ideas/Show')
            ->where('idea.id', $idea->id)
            ->where('idea.details', $idea->details)
            ->where('idea.details_html', fn (string $html) => str_contains($html, '<h2>Hook</h2>')
                && str_contains($html, '<li>One</li>')
                && str_contains($html, '<code>code</code>')
                && str_contains($html, '<a href="https://example.com">a link</a>'))
        );
});

test('the show page escapes raw html and drops unsafe links', function () {
    $idea = ContentIdea::factory()->create([
        'workspace_id' => $this->workspace->id,
        'details' => "<script>alert(1)</script>\n\n[click](javascript:alert(1)) <img src=x onerror=alert(1)>",
    ]);

    $this->actingAs($this->user)
        ->get(route('app.ideas.show', $idea))
        ->assertInertia(fn ($page) => $page
            ->where('idea.details_html', fn (string $html) => str_contains($html, '&lt;script&gt;')
                && ! str_contains($html, '<script')
                && ! str_contains($html, '<img')
                && ! str_contains($html, 'href="javascript:'))
        );
});

test('the show page returns 404 for another workspace idea', function () {
    $this->actingAs($this->user)
        ->get(route('app.ideas.show', ContentIdea::factory()->create()))
        ->assertNotFound();
});

// Update
test('updating an idea changes its title and details only', function () {
    $idea = ContentIdea::factory()->inProgress()->viaMcp()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->from(route('app.ideas.show', $idea))
        ->put(route('app.ideas.update', $idea), ['title' => 'Sharper title', 'details' => 'New brief', 'status' => 'done'])
        ->assertRedirect(route('app.ideas.show', $idea));

    $idea->refresh();

    expect($idea->title)->toBe('Sharper title')
        ->and($idea->details)->toBe('New brief')
        ->and($idea->status)->toBe(Status::InProgress)
        ->and($idea->created_via)->toBe(CreatedVia::Mcp);
});

test('updating an idea validates the title and details', function (array $payload, string $field) {
    $idea = ContentIdea::factory()->create(['workspace_id' => $this->workspace->id, 'title' => 'Original']);

    $this->actingAs($this->user)
        ->put(route('app.ideas.update', $idea), $payload)
        ->assertSessionHasErrors($field);

    expect($idea->refresh()->title)->toBe('Original');
})->with([
    'missing title' => [['title' => ''], 'title'],
    'title too long' => [['title' => str_repeat('a', 256)], 'title'],
    'details too long' => [['title' => 'Fine', 'details' => str_repeat('a', 20001)], 'details'],
]);

test('updating another workspace idea returns 404', function () {
    $foreign = ContentIdea::factory()->create(['title' => 'Theirs']);

    $this->actingAs($this->user)
        ->put(route('app.ideas.update', $foreign), ['title' => 'Mine now'])
        ->assertNotFound();

    expect($foreign->refresh()->title)->toBe('Theirs');
});

// Destroy
test('deleting an idea removes it and returns to the list', function () {
    $idea = ContentIdea::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->delete(route('app.ideas.destroy', $idea))
        ->assertRedirect(route('app.ideas.index'));

    $this->assertModelMissing($idea);
});

test('deleting another workspace idea returns 404', function () {
    $foreign = ContentIdea::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('app.ideas.destroy', $foreign))
        ->assertNotFound();

    $this->assertModelExists($foreign);
});
