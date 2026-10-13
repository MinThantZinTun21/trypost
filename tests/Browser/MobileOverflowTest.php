<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\ContentIdea;
use App\Models\LibraryItem;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Guards against horizontal overflow on a phone and a desktop across the
 * layout variants (AuthLayout, default AppLayout, full-width editor), and
 * saves a screenshot of each page for design review. An element past the
 * right edge fails too, unless it sits in an `overflow-x-auto` container, because
 * SidebarInset clips overflow instead of scrolling the document.
 * Reproduces the AuthSplitLayout long-name overflow that a fixed grid column
 * caused. Returns the widest offending element on failure for quick triage.
 */
function assertNoHorizontalOverflow(mixed $page, string $label): void
{
    $result = $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 80; i++) {
                if (document.querySelector('h1, h2, main, [data-sidebar="trigger"]')) break;
                await new Promise((r) => setTimeout(r, 50));
            }
            await new Promise((r) => setTimeout(r, 400));
            const vw = window.innerWidth;
            const scrolls = (el) => !!el.parentElement?.closest('[class*="overflow-x-auto"], [class*="overflow-x-scroll"]');
            let worst = null;
            document.querySelectorAll('*').forEach((el) => {
                const r = el.getBoundingClientRect();
                if (r.width > 0 && r.height > 0 && r.right > vw + 2 && (!worst || r.right > worst.right) && !scrolls(el)) {
                    worst = { right: Math.round(r.right), tag: el.tagName.toLowerCase(), cls: (el.getAttribute('class') || '').slice(0, 80) };
                }
            });
            return { sw: document.documentElement.scrollWidth, iw: vw, worst };
        })()
    JS);

    $sw = (int) ($result['sw'] ?? 0);
    $iw = (int) ($result['iw'] ?? 375);
    $worst = $result['worst'] ?? null;
    $detail = $worst ? " widest: <{$worst['tag']} class=\"{$worst['cls']}\"> right={$worst['right']}" : '';

    expect($sw - $iw)->toBeLessThanOrEqual(2, "{$label} overflows: scrollWidth {$sw} > innerWidth {$iw}.{$detail}")
        ->and($worst)->toBeNull("{$label} has an element past the right edge.{$detail}");
}

/**
 * An Owner with a long workspace name, which previously stretched the
 * AuthSplitLayout grid column past the viewport, and one draft Post.
 *
 * @return array{0: User, 1: Workspace, 2: Post}
 */
function createMobileOverflowOwner(int $platforms = 1): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'name' => 'A Really Very Extremely Long Workspace Name That Should Truncate Instead Of Overflowing',
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'Launching our new mobile editor today!',
    ]);
    PostPlatform::factory()->count($platforms)->create(['post_id' => $post->id]);

    return [$user, $workspace, $post];
}

test('key pages do not overflow horizontally', function (int $width, int $height) {
    [$user, $workspace, $post] = createMobileOverflowOwner(platforms: 2);
    SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    LibraryItem::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    ContentIdea::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($user);

    $pages = [
        'posts' => route('app.posts.index'),
        'post-show' => route('app.posts.show', $post),
        'post-editor' => route('app.posts.edit', $post),
        'calendar' => route('app.calendar'),
        'library' => route('app.library.index'),
        'ideas' => route('app.ideas.index'),
        'insights' => route('app.insights.index'),
        'accounts' => route('app.accounts'),
        'settings' => route('app.settings'),
        'profile' => route('app.profile.edit'),
    ];

    foreach ($pages as $label => $url) {
        $page = visit($url)->resize($width, $height);

        assertNoHorizontalOverflow($page, "{$label} at {$width}px");

        $page->screenshot(filename: "page-{$label}-{$width}");

        $page->assertNoJavaScriptErrors();
    }
})->with([
    'phone' => [375, 812],
    'desktop' => [1280, 860],
]);

test('the help button does not cover the editor action bar on a phone', function () {
    [$user, , $post] = createMobileOverflowOwner();

    $this->actingAs($user);

    $page = visit(route('app.posts.edit', $post))->resize(375, 812);

    $overlap = $page->script(<<<'JS'
        (async () => {
            const find = () => [
                document.querySelector('[data-testid="help-menu-trigger"]'),
                document.querySelector('[data-testid="editor-action-bar"]'),
            ];
            for (let i = 0; i < 80; i++) {
                const [help, bar] = find();
                if (help && bar && bar.getBoundingClientRect().height > 0) break;
                await new Promise((r) => setTimeout(r, 50));
            }
            await new Promise((r) => setTimeout(r, 200));
            const [help, bar] = find();
            if (!help || !bar) return 'missing';
            return help.getBoundingClientRect().bottom > bar.getBoundingClientRect().top;
        })()
    JS);

    expect($overlap)->toBeFalse();

    $page->assertNoJavaScriptErrors();
});
