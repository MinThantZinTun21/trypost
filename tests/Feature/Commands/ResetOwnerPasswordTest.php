<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it resets the owner password from the interactive prompt', function () {
    $owner = User::factory()->create();

    $this->artisan('owner:reset-password')
        ->expectsQuestion('New password', 'brand-new-secret')
        ->expectsQuestion('Confirm new password', 'brand-new-secret')
        ->expectsOutputToContain("Password updated for {$owner->email}.")
        ->assertSuccessful();

    expect(Hash::check('brand-new-secret', $owner->refresh()->password))->toBeTrue();
});

test('it accepts the password as an option for non-interactive use', function () {
    $owner = User::factory()->create();

    $this->artisan('owner:reset-password', ['--password' => 'brand-new-secret'])
        ->assertSuccessful();

    expect(Hash::check('brand-new-secret', $owner->refresh()->password))->toBeTrue();
});

test('it targets the user given by --email', function () {
    $first = User::factory()->create();
    $second = User::factory()->create(['email' => 'second@example.com']);

    $this->artisan('owner:reset-password', ['--email' => 'second@example.com', '--password' => 'brand-new-secret'])
        ->assertSuccessful();

    expect(Hash::check('brand-new-secret', $second->refresh()->password))->toBeTrue()
        ->and(Hash::check('password', $first->refresh()->password))->toBeTrue();
});

test('it fails when no user exists', function () {
    $this->artisan('owner:reset-password', ['--password' => 'brand-new-secret'])
        ->expectsOutputToContain('No user exists yet')
        ->assertFailed();
});

test('it fails when the given email does not match a user', function () {
    User::factory()->create();

    $this->artisan('owner:reset-password', ['--email' => 'nobody@example.com', '--password' => 'brand-new-secret'])
        ->expectsOutputToContain('No user found with email nobody@example.com.')
        ->assertFailed();
});

test('it rejects a confirmation that does not match', function () {
    $owner = User::factory()->create();

    $this->artisan('owner:reset-password')
        ->expectsQuestion('New password', 'brand-new-secret')
        ->expectsQuestion('Confirm new password', 'something-else')
        ->assertFailed();

    expect(Hash::check('password', $owner->refresh()->password))->toBeTrue();
});

test('it accepts any password, even a short one in production', function () {
    $owner = User::factory()->create();
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('owner:reset-password', ['--password' => '1234'])
        ->assertSuccessful();

    expect(Hash::check('1234', $owner->refresh()->password))->toBeTrue();
});

test('it rejects an empty password', function () {
    $owner = User::factory()->create();

    $this->artisan('owner:reset-password')
        ->expectsQuestion('New password', '')
        ->expectsQuestion('Confirm new password', '')
        ->assertFailed();

    expect(Hash::check('password', $owner->refresh()->password))->toBeTrue();
});
