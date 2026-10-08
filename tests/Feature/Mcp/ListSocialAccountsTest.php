<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Mcp\Servers\SchedulerServer;
use App\Mcp\Tools\ListSocialAccounts;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('it lists the owner\'s social accounts with their content types', function () {
    $page = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Lucky News']);
    SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id]);
    SocialAccount::factory()->youtube()->disconnected()->create(['workspace_id' => $this->workspace->id]);

    $response = SchedulerServer::actingAs($this->owner)->tool(ListSocialAccounts::class);

    $response->assertOk()
        ->assertSee('Lucky News')
        ->assertStructuredContent(function ($json) use ($page) {
            $json->has('social_accounts', 3)
                ->where('social_accounts.0.id', $page->id)
                ->where('social_accounts.0.platform', Platform::Facebook->value)
                ->where('social_accounts.0.display_name', 'Lucky News')
                ->where('social_accounts.0.status', 'connected')
                ->where('social_accounts.0.content_types', ['facebook_post', 'facebook_reel', 'facebook_story'])
                ->where('social_accounts.2.status', 'disconnected')
                ->etc();
        });
});

test('it never lists accounts from another workspace', function () {
    SocialAccount::factory()->facebook()->create();

    SchedulerServer::actingAs($this->owner)->tool(ListSocialAccounts::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('social_accounts', 0));
});
