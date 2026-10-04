<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('connect and callback routes for removed platforms are gone', function (string $method, string $uri) {
    $this->actingAs($this->user)->json($method, $uri)->assertNotFound();
})->with([
    'linkedin connect' => ['GET', '/connect/linkedin'],
    'linkedin callback' => ['GET', '/accounts/linkedin/callback'],
    'x connect' => ['GET', '/connect/x'],
    'x callback' => ['GET', '/accounts/x/callback'],
    'instagram connect' => ['GET', '/connect/instagram'],
    'instagram callback' => ['GET', '/accounts/instagram/callback'],
    'instagram via facebook connect' => ['GET', '/connect/instagram-facebook'],
    'instagram via facebook callback' => ['GET', '/accounts/instagram-facebook/callback'],
    'threads connect' => ['GET', '/connect/threads'],
    'threads callback' => ['GET', '/accounts/threads/callback'],
    'pinterest connect' => ['GET', '/connect/pinterest'],
    'pinterest callback' => ['GET', '/accounts/pinterest/callback'],
    'bluesky connect' => ['GET', '/connect/bluesky'],
    'bluesky store' => ['POST', '/connect/bluesky'],
    'mastodon connect' => ['GET', '/connect/mastodon'],
    'mastodon callback' => ['GET', '/accounts/mastodon/callback'],
    'telegram connect' => ['POST', '/connect/telegram'],
    'telegram webhook' => ['POST', '/telegram/webhook'],
    'discord connect' => ['GET', '/connect/discord'],
    'discord callback' => ['GET', '/accounts/discord/callback'],
    'google business connect' => ['GET', '/connect/google-business'],
    'google business callback' => ['GET', '/accounts/google-business/callback'],
]);

test('no route is named for a removed platform or the link preview', function () {
    $names = collect(Route::getRoutes()->getRoutesByName())->keys();

    expect($names->filter(fn (string $name): bool => (bool) preg_match(
        '/instagram|threads|linkedin|pinterest|bluesky|mastodon|telegram|discord|google-business|\\.x\\.|link-preview/',
        $name,
    ))->all())->toBe([]);
});

test('the connect-account page offers only facebook, tiktok and youtube', function () {
    $this->actingAs($this->user)
        ->get(route('app.accounts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/Index', false)
            ->where('platforms', fn ($platforms): bool => collect($platforms)->pluck('value')->sort()->values()->all()
                === ['facebook', 'tiktok', 'youtube'])
        );
});

test('the removed platform composer packages are not required', function () {
    $require = json_decode((string) file_get_contents(base_path('composer.json')), true)['require'];

    expect($require)
        ->not->toHaveKey('socialiteproviders/instagram')
        ->not->toHaveKey('socialiteproviders/linkedin')
        ->not->toHaveKey('socialiteproviders/pinterest')
        ->not->toHaveKey('socialiteproviders/twitter');
});

test('the data migration deletes social accounts and post platforms of removed platforms', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $keptAccount = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);
    $keptTarget = PostPlatform::factory()->facebook()->create([
        'post_id' => $post->id,
        'social_account_id' => $keptAccount->id,
    ]);

    $removedAccountId = (string) Str::uuid();
    DB::table('social_accounts')->insert([
        'id' => $removedAccountId,
        'workspace_id' => $this->workspace->id,
        'platform' => 'linkedin',
        'platform_user_id' => 'linkedin-member',
        'access_token' => 'token',
        'status' => 'connected',
        'is_active' => true,
    ]);

    $insertTarget = function (?string $socialAccountId, string $platform, string $contentType, string $status = 'pending') use ($post): string {
        $id = (string) Str::uuid();

        DB::table('post_platforms')->insert([
            'id' => $id,
            'post_id' => $post->id,
            'social_account_id' => $socialAccountId,
            'platform' => $platform,
            'content_type' => $contentType,
            'status' => $status,
            'enabled' => true,
        ]);

        return $id;
    };

    $removedPlatformTarget = $insertTarget($removedAccountId, 'linkedin', 'linkedin_post');
    $removedContentTypeTarget = $insertTarget($keptAccount->id, 'facebook', 'instagram_feed');
    $orphanedRemovedTarget = $insertTarget(null, 'x', 'x_post', 'published');
    $reviewTarget = $insertTarget($keptAccount->id, 'facebook', 'facebook_post', 'pending_review');

    $migration = require database_path('migrations/2026_10_02_093736_remove_unused_platforms_data.php');
    $migration->up();

    expect(DB::table('social_accounts')->where('id', $removedAccountId)->exists())->toBeFalse()
        ->and(DB::table('post_platforms')->whereIn('id', [
            $removedPlatformTarget,
            $removedContentTypeTarget,
            $orphanedRemovedTarget,
        ])->exists())->toBeFalse()
        ->and(DB::table('post_platforms')->where('id', $reviewTarget)->value('status'))->toBe('failed')
        ->and($keptAccount->fresh())->not->toBeNull()
        ->and($keptTarget->fresh())->not->toBeNull();

    $this->actingAs($this->user)->get(route('app.posts.edit', $post))->assertOk();
    $this->actingAs($this->user)->get(route('app.accounts'))->assertOk();
});
