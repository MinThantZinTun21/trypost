<?php

declare(strict_types=1);

use App\Enums\Mobile\PostTab;
use App\Enums\Post\Status;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\ListPosts;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('there is no Draft status or Drafts tab', function () {
    expect(Status::tryFrom('draft'))->toBeNull()
        ->and(PostTab::tryFrom('draft'))->toBeNull()
        ->and(method_exists(Post::class, 'scopeDraft'))->toBeFalse();
});

test('the web Drafts list is gone', function () {
    $this->actingAs($this->owner)->get('/posts/draft')->assertNotFound();
});

test('the assistant can neither save nor list Drafts', function () {
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id]);

    SchedulerServer::actingAs($this->owner)
        ->tool(CreatePost::class, [
            'mode' => 'draft',
            'content' => 'Not yet',
            'platforms' => [['social_account_id' => $account->id]],
        ])
        ->assertHasErrors();

    SchedulerServer::actingAs($this->owner)
        ->tool(ListPosts::class, ['status' => 'draft'])
        ->assertHasErrors();

    expect(Post::query()->count())->toBe(0);
});

test('the Mobile app has no Drafts tab and cannot Unschedule', function () {
    $headers = mobileHeaders($this->owner);
    $post = Post::factory()->scheduled()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->getJson(route('mobile.v1.posts.index', ['status' => 'draft']), $headers)->assertUnprocessable();
    $this->deleteJson("/mobile/v1/posts/{$post->id}/schedule", [], $headers)->assertMethodNotAllowed();

    expect($post->fresh()->status)->toBe(Status::Scheduled);
});

test('the Unschedule action is gone', function () {
    expect(class_exists('App\\Actions\\Post\\UnschedulePost'))->toBeFalse();
});
