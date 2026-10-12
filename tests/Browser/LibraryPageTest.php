<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\LibraryItem;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Wait for a data-testid element to mount and lay out; see AccountsPageTest.
 */
function waitForLibraryTestId(mixed $page, string $testId): void
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

function libraryOwner(): User
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

test('the library lists items and saves a new one from the dialog', function () {
    $user = libraryOwner();
    $item = LibraryItem::factory()->viaMcp()->create(['workspace_id' => $user->current_workspace_id, 'name' => 'Desk tour']);

    $this->actingAs($user);

    $page = visit(route('app.library.index'));

    waitForLibraryTestId($page, "library-item-{$item->id}");

    $page->assertVisible("@library-item-{$item->id}")
        ->assertSeeIn("@library-item-used-{$item->id}", 'Not used yet')
        ->click('@library-new');

    waitForLibraryTestId($page, 'library-name-input');

    $page->type('@library-name-input', 'Ramen in five minutes')
        ->type('@library-facebook-text-input', 'Dinner is ready.')
        ->type('@library-tiktok-caption-input', 'POV: ramen #food')
        ->type('@library-youtube-title-input', 'Ramen in five minutes')
        ->type('@library-youtube-tags-input', 'ramen, quick dinner')
        ->click('@library-save');

    $created = null;
    for ($attempt = 0; $attempt < 50 && $created === null; $attempt++) {
        $page->script('new Promise((r) => setTimeout(r, 100))');
        $created = LibraryItem::query()->where('name', 'Ramen in five minutes')->first();
    }

    expect($created)->not->toBeNull()
        ->and($created->youtube_tags)->toBe(['ramen', 'quick dinner']);

    waitForLibraryTestId($page, "library-item-{$created->id}");

    $page->assertVisible("@library-item-{$created->id}")
        ->assertNoJavaScriptErrors();
});

test('scheduling an item opens its Draft in the editor', function () {
    $user = libraryOwner();
    $account = SocialAccount::factory()->youtube()->create(['workspace_id' => $user->current_workspace_id]);
    $item = LibraryItem::factory()->create(['workspace_id' => $user->current_workspace_id]);

    $this->actingAs($user);

    $page = visit(route('app.library.index'));

    waitForLibraryTestId($page, "library-item-schedule-{$item->id}");

    $page->click("@library-item-schedule-{$item->id}");

    waitForLibraryTestId($page, "library-schedule-account-{$account->id}");

    $page->click('@library-schedule-submit');

    $post = null;
    for ($attempt = 0; $attempt < 50 && $post === null; $attempt++) {
        $page->script('new Promise((r) => setTimeout(r, 100))');
        $post = Post::query()->where('library_item_id', $item->id)->first();
    }

    expect($post)->not->toBeNull();

    $page->assertPathIs(parse_url(route('app.posts.edit', $post), PHP_URL_PATH))
        ->assertNoJavaScriptErrors();
});

test('the library fits the screen at every width', function (int $width, int $height) {
    $user = libraryOwner();
    SocialAccount::factory()->facebook()->create(['workspace_id' => $user->current_workspace_id]);
    $items = LibraryItem::factory()->count(3)->create(['workspace_id' => $user->current_workspace_id]);

    $this->actingAs($user);

    $page = visit(route('app.library.index'))->resize($width, $height);

    waitForLibraryTestId($page, "library-item-{$items->first()->id}");

    $overflow = $page->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');

    expect($overflow)->toBeLessThanOrEqual(0);

    $page->screenshot(filename: "library-{$width}");

    $page->click('@library-new');
    waitForLibraryTestId($page, 'library-name-input');
    $page->screenshot(filename: "library-dialog-{$width}");

    $page->assertNoJavaScriptErrors();
})->with([
    'desktop' => [1280, 860],
    'phone' => [375, 812],
]);
