<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('invite, member, workspace-management and comment endpoints are gone', function (string $method, string $uri) {
    $uri = str_replace(
        ['{workspace}', '{post}', '{id}'],
        [$this->workspace->id, Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id])->id, (string) Str::uuid()],
        $uri,
    );

    $this->actingAs($this->owner)->json($method, $uri)->assertNotFound();
})->with([
    'invite show' => ['GET', '/invites/{id}'],
    'invite accept' => ['POST', '/invites/{id}/accept'],
    'invite decline' => ['POST', '/invites/{id}/decline'],
    'members' => ['GET', '/settings/workspace/members'],
    'invite store' => ['POST', '/settings/workspace/members/invites'],
    'invite destroy' => ['DELETE', '/settings/workspace/members/invites/{id}'],
    'member remove' => ['DELETE', '/settings/workspace/members/{id}'],
    'member role' => ['PUT', '/settings/workspace/members/{id}/role'],
    'member search' => ['GET', '/workspace/members/search'],
    'workspaces index' => ['GET', '/workspaces'],
    'workspaces create' => ['GET', '/workspaces/create'],
    'workspaces store' => ['POST', '/workspaces'],
    'workspaces switch' => ['POST', '/workspaces/{workspace}/switch'],
    'workspaces destroy' => ['DELETE', '/workspaces/{workspace}'],
    'workspace settings' => ['GET', '/settings/workspace'],
    'workspace settings update' => ['PUT', '/settings/workspace'],
    'workspace logo upload' => ['POST', '/settings/workspace/logo'],
    'workspace logo delete' => ['DELETE', '/settings/workspace/logo'],
    'comments index' => ['GET', '/posts/{post}/comments'],
    'comments store' => ['POST', '/posts/{post}/comments'],
    'comments update' => ['PUT', '/posts/{post}/comments/{id}'],
    'comments destroy' => ['DELETE', '/posts/{post}/comments/{id}'],
    'comments react' => ['POST', '/posts/{post}/comments/{id}/react'],
]);

test('the removed route names are no longer registered', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'app.invites.show',
    'app.invites.accept',
    'app.invites.decline',
    'app.invites.store',
    'app.invites.destroy',
    'app.members',
    'app.members.remove',
    'app.members.update-role',
    'app.workspace.members.search',
    'app.workspaces.index',
    'app.workspaces.create',
    'app.workspaces.store',
    'app.workspaces.switch',
    'app.workspaces.destroy',
    'app.workspace.settings',
    'app.workspace.settings.update',
    'app.posts.comments.index',
    'app.posts.comments.store',
]);

test('the invite and comment tables are dropped', function (string $table) {
    expect(Schema::hasTable($table))->toBeFalse();
})->with(['invites', 'post_comments']);

test('the Owner calendar loads without workspace switcher or role props', function () {
    $this->actingAs($this->owner)
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Calendar')
            ->where('auth.currentWorkspace.id', $this->workspace->id)
            ->missing('auth.workspaces')
            ->missing('auth.currentWorkspace.role')
            ->missing('auth.currentWorkspace.logo_url'),
        );
});

test('the Owner posts list loads', function () {
    Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.posts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('posts/Index'));
});

test('the Owner post editor loads without comment props', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.posts.edit', $post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Edit')
            ->missing('authUserId'),
        );
});

test('the Owner social accounts page loads', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.accounts'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('accounts/Index'));
});

test('the settings hub only offers the profile', function () {
    $this->actingAs($this->owner)
        ->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Index')
            ->missing('permissions'),
        );
});
