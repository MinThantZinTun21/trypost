<?php

declare(strict_types=1);

use App\Enums\Post\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

function seedTikTokTelegramEditor(): Post
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'For the channel', 'status' => Status::Scheduled, 'scheduled_at' => now()->addDay()]);
    $account = SocialAccount::factory()->tiktok()->create(['workspace_id' => $workspace->id]);
    PostPlatform::factory()->tiktok()->create(['post_id' => $post->id, 'social_account_id' => $account->id, 'enabled' => true]);
    test()->actingAs($user);

    return $post;
}

function waitForTikTokTelegramTestId(mixed $page, string $testId): void
{
    $page->script(<<<JS
        (async () => {
            for (let i = 0; i < 100; i++) {
                const el = document.querySelector('[data-testid="{$testId}"]');
                if (el && el.getBoundingClientRect().height > 0) return;
                await new Promise(resolve => setTimeout(resolve, 50));
            }
        })();
    JS);
}

function openTikTokSettings(mixed $page): void
{
    waitForTikTokTelegramTestId($page, 'tiktok-settings-toggle-0');
    $page->click('@tiktok-settings-toggle-0');
    waitForTikTokTelegramTestId($page, 'tiktok-caption-0');
}

test('the TikTok settings say the post goes to Telegram and ask for no privacy level', function () {
    config(['trypost.platforms.tiktok.delivery' => 'telegram']);
    $page = visit(route('app.posts.edit', seedTikTokTelegramEditor()));
    openTikTokSettings($page);

    $page->assertVisible('@tiktok-via-telegram')
        ->assertSee(__('posts.form.tiktok.via_telegram'))
        ->assertMissing('@tiktok-privacy-level-0')
        ->assertNoJavaScriptErrors();
});

test('with API delivery the TikTok settings ask for a privacy level and show no Telegram note', function () {
    $page = visit(route('app.posts.edit', seedTikTokTelegramEditor()));
    openTikTokSettings($page);

    $page->assertVisible('@tiktok-privacy-level-0')
        ->assertMissing('@tiktok-via-telegram')
        ->assertNoJavaScriptErrors();
});
