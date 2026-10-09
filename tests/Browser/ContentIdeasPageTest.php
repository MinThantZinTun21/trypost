<?php

declare(strict_types=1);

use App\Enums\ContentIdea\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\ContentIdea;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out. Assertions do not
 * auto-wait on SPA paint, and a blocking sleep() would starve the asset server.
 */
function waitForContentIdeasTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            const sel = '[data-testid="{$testId}"]';
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector(sel);
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise((r) => setTimeout(r, 50));
            }
        })();
    JS);
}

function contentIdeasOwner(): User
{
    $user = User::factory()->create();

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

test('the content ideas page lists ideas without javascript errors', function () {
    $user = contentIdeasOwner();
    $idea = ContentIdea::factory()->viaMcp()->create([
        'workspace_id' => $user->current_workspace_id,
        'title' => 'Desk tour reel',
        'details' => "## Hook\n\nOpen on the lamp.",
        'status' => Status::InProgress,
    ]);

    $this->actingAs($user);

    $page = visit(route('app.ideas.index'));

    waitForContentIdeasTestId($page, "idea-row-{$idea->id}");

    $page->assertVisible("@idea-row-{$idea->id}")
        ->assertVisible('@ideas-new')
        ->assertVisible('@ideas-filter-done')
        ->assertNoJavaScriptErrors();
});

test('an idea page renders its markdown without javascript errors', function () {
    $user = contentIdeasOwner();
    $idea = ContentIdea::factory()->create([
        'workspace_id' => $user->current_workspace_id,
        'details' => "## Hook\n\n- Open on the lamp",
    ]);

    $this->actingAs($user);

    $page = visit(route('app.ideas.show', $idea));

    waitForContentIdeasTestId($page, 'idea-details');

    $page->assertVisible('@idea-details')
        ->assertVisible("@idea-status-{$idea->id}")
        ->assertNoJavaScriptErrors();
});
