<?php

declare(strict_types=1);

use App\Models\MobileToken;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->owner = User::factory()->create();
    Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('signing out revokes only the mobile token that made the call', function () {
    $thisPhone = mobileHeaders($this->owner);
    $otherPhone = mobileHeaders($this->owner);

    $this->deleteJson(route('mobile.v1.session.destroy'), [], $thisPhone)->assertNoContent();

    $this->getJson(route('mobile.v1.me'), $thisPhone)->assertUnauthorized();
    $this->getJson(route('mobile.v1.me'), $otherPhone)->assertOk();
    expect(MobileToken::query()->count())->toBe(1);
});

test('signing out needs a mobile token', function () {
    $this->deleteJson(route('mobile.v1.session.destroy'))->assertUnauthorized();
});

test('changing the password in web settings revokes every mobile token', function () {
    $headers = mobileHeaders($this->owner);
    mobileHeaders($this->owner);

    $this->actingAs($this->owner)
        ->put(route('app.authentication.update-password'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(MobileToken::query()->count())->toBe(0);
    $this->getJson(route('mobile.v1.me'), $headers)->assertUnauthorized();
});

test('resetting the owner password on the server revokes every mobile token', function () {
    mobileHeaders($this->owner);

    $this->artisan('owner:reset-password', ['--password' => 'brand-new-secret'])->assertSuccessful();

    expect(MobileToken::query()->count())->toBe(0);
});

test('saving the owner without changing the password keeps their mobile tokens', function () {
    mobileHeaders($this->owner);

    $this->owner->update(['name' => 'A new name']);

    expect(MobileToken::query()->count())->toBe(1);
});

test('a web sign in that only rehashes the same password keeps the mobile tokens', function () {
    $this->owner->update(['email' => 'owner@example.com']);
    mobileHeaders($this->owner);
    config(['hashing.bcrypt.rounds' => 5]);
    Hash::forgetDrivers();

    $this->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'password'])->assertSessionHasNoErrors();

    expect(Hash::info($this->owner->fresh()->password)['options']['cost'])->toBe(5)
        ->and(MobileToken::query()->count())->toBe(1);
});
