<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('trypost.mobile.token', 'mobile-test-token');

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->headers = ['Authorization' => 'Bearer mobile-test-token'];
});

test('the social accounts are listed in calendar order, disconnected ones included', function () {
    $youtube = SocialAccount::factory()->youtube()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Alpha']);
    $facebookB = SocialAccount::factory()->facebook()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Bravo']);
    $facebookA = SocialAccount::factory()->facebook()->disconnected()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Alpha']);
    $tiktok = SocialAccount::factory()->tiktok()->create(['workspace_id' => $this->workspace->id, 'display_name' => 'Zulu']);
    SocialAccount::factory()->create(['display_name' => 'Someone else\'s']);

    $response = $this->getJson(route('mobile.v1.social-accounts.index'), $this->headers)
        ->assertOk()
        ->assertJsonPath('data.*.id', [$facebookA->id, $facebookB->id, $tiktok->id, $youtube->id]);

    expect($response->json('data.0'))->toBe([
        'id' => $facebookA->id,
        'platform' => Platform::Facebook->value,
        'display_label' => $facebookA->display_label,
        'status' => 'disconnected',
    ]);
});

test('the social accounts need the token', function () {
    $this->getJson(route('mobile.v1.social-accounts.index'))->assertUnauthorized();
});
