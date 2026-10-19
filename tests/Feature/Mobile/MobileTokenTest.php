<?php

declare(strict_types=1);

use App\Models\MobileToken;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'The Owner']);
    Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('the mobile api rejects a missing or unknown token', function (array $headers) {
    $this->getJson(route('mobile.v1.me'), $headers)->assertUnauthorized();
})->with([
    'no token' => [[]],
    'empty token' => [['Authorization' => 'Bearer ']],
    'unknown token' => [['Authorization' => 'Bearer nope']],
]);

test('the stored hash does not open the mobile api', function () {
    mobileHeaders($this->owner);
    $storedHash = MobileToken::query()->sole()->token_hash;

    $this->getJson(route('mobile.v1.me'), ['Authorization' => "Bearer {$storedHash}"])
        ->assertUnauthorized();
});

test('a deleted mobile token no longer opens the mobile api', function () {
    $headers = mobileHeaders($this->owner);
    MobileToken::query()->delete();

    $this->getJson(route('mobile.v1.me'), $headers)->assertUnauthorized();
});

test('the mcp token does not open the mobile api', function () {
    config()->set('trypost.mcp.token', 'shared-secret');

    $this->getJson(route('mobile.v1.me'), ['Authorization' => 'Bearer shared-secret'])->assertUnauthorized();
});

test('me returns the owner of the mobile token', function () {
    User::factory()->create(['name' => 'Someone later']);

    $this->getJson(route('mobile.v1.me'), mobileHeaders($this->owner))
        ->assertOk()
        ->assertExactJson(['name' => 'The Owner']);
});

test('the mobile api keeps no session', function () {
    $this->getJson(route('mobile.v1.me'), mobileHeaders($this->owner))
        ->assertOk()
        ->assertCookieMissing(config('session.cookie'));
});

test('the owner is the first user even when a later one shares their creation second', function () {
    $this->freezeSecond();
    User::factory()->count(3)->create(['created_at' => $this->owner->created_at]);

    expect(User::findOwner()?->is($this->owner))->toBeTrue();
});
