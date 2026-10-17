<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config()->set('trypost.mobile.token', 'mobile-test-token');

    $this->owner = User::factory()->create(['name' => 'The Owner']);
    Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('the mobile api is off when no token is configured', function (string $route) {
    config()->set('trypost.mobile.token', '');

    $this->getJson(route($route, ['status' => 'scheduled']), ['Authorization' => 'Bearer mobile-test-token'])->assertNotFound();
})->with(['mobile.v1.me', 'mobile.v1.posts.index']);

test('the mobile api rejects a missing or wrong token', function (array $headers) {
    $this->getJson(route('mobile.v1.me'), $headers)->assertUnauthorized();
})->with([
    'no token' => [[]],
    'wrong token' => [['Authorization' => 'Bearer nope']],
]);

test('the mcp token does not open the mobile api', function () {
    config()->set('trypost.mcp.token', 'shared-secret');

    $this->getJson(route('mobile.v1.me'), ['Authorization' => 'Bearer shared-secret'])->assertUnauthorized();
});

test('me returns the owner with the right token', function () {
    User::factory()->create(['name' => 'Someone later']);

    $this->getJson(route('mobile.v1.me'), ['Authorization' => 'Bearer mobile-test-token'])
        ->assertOk()
        ->assertExactJson(['name' => 'The Owner']);
});

test('the mobile api keeps no session', function () {
    $this->getJson(route('mobile.v1.me'), ['Authorization' => 'Bearer mobile-test-token'])
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'));
});

test('the owner is the first user even when a later one shares their creation second', function () {
    $this->freezeSecond();
    User::factory()->count(3)->create(['created_at' => $this->owner->created_at]);

    $this->getJson(route('mobile.v1.me'), ['Authorization' => 'Bearer mobile-test-token'])
        ->assertOk()
        ->assertExactJson(['name' => 'The Owner']);
});
