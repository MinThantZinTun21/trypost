<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{original: Post, account: SocialAccount}
 */
function seedCreatePostComposer(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $original = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'Worth posting twice',
        'status' => Status::Published,
    ]);
    PostPlatform::factory()->create([
        'post_id' => $original->id,
        'social_account_id' => $account->id,
        'content_type' => ContentType::FacebookPost,
        'enabled' => true,
    ]);
    test()->actingAs($user);

    return ['original' => $original, 'account' => $account];
}

function waitForCreatePostTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector('[data-testid="{$testId}"]');
                if (el && el.getBoundingClientRect().height > 0 && !el.disabled) return;
                await new Promise(resolve => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function waitForCreatePostToLeave(mixed $page): void
{
    $page->script(<<<'JS'
        (async () => {
            for (let i = 0; i < 100; i++) {
                if (! window.location.pathname.endsWith('/posts/create')) return;
                await new Promise(resolve => setTimeout(resolve, 50));
            }
        })();
    JS);
}

test('a duplicated Post is only saved when it is scheduled', function () {
    ['original' => $original, 'account' => $account] = seedCreatePostComposer();
    $day = now()->addDays(3)->format('Y-m-d');

    $page = visit(route('app.posts.create', ['duplicate' => $original->id, 'date' => $day]));
    waitForCreatePostTestId($page, 'post-submit');

    expect(Post::query()->count())->toBe(1);

    $page->assertSee(__('posts.edit.new_post'))
        ->assertSee(__('posts.edit.schedule'))
        ->assertMissing('@post-delete');

    $page->click('@post-submit');
    waitForCreatePostToLeave($page);

    $copy = Post::query()->whereKeyNot($original->id)->sole();

    expect($copy->status)->toBe(Status::Scheduled)
        ->and($copy->content)->toBe('Worth posting twice')
        ->and($copy->scheduled_at->utc()->format('Y-m-d H:i'))->toBe("{$day} 09:00")
        ->and($copy->postPlatforms()->enabled()->sole()->social_account_id)->toBe($account->id);

    $page->assertNoJavaScriptErrors();
});
