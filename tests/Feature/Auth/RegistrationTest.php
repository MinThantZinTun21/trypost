<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;

function createTestInvite(string $email): Invite
{
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    return Invite::factory()->create([
        'account_id' => $account->id,
        'invited_by' => $owner->id,
        'email' => $email,
        'workspaces' => [$workspace->id],
    ]);
}

test('new users registering via invite have verified email automatically', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $invite = Invite::factory()->create([
        'account_id' => $account->id,
        'invited_by' => $owner->id,
        'email' => 'test@example.com',
        'workspaces' => [$workspace->id],
    ]);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'invite' => $invite->id,
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});

test('register page returns 404 without a pending invite in session', function () {
    $response = $this->get(route('register'));

    $response->assertNotFound();
});

test('register POST returns 404 without a pending invite in session', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertNotFound();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('register page renders when session has pending invite', function () {
    $invite = createTestInvite('invitee@example.com');

    $response = $this
        ->withSession(['pending_invite_id' => $invite->id])
        ->get(route('register'));

    $response->assertOk();
});

test('register page renders with invite query param and persists it to session', function () {
    $invite = createTestInvite('invitee@example.com');

    $response = $this->get(route('register', ['invite' => $invite->id]));

    $response->assertOk();
    $response->assertSessionHas('pending_invite_id', $invite->id);
});

test('signup clears pending_invite_id from session', function () {
    $invite = createTestInvite('invitee@example.com');

    $this->withSession(['pending_invite_id' => $invite->id])
        ->post(route('register.store'), [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => 'Password123!',
        ]);

    expect(session('pending_invite_id'))->toBeNull();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
});

test('register POST passes with invite query param even without prior session', function () {
    $invite = createTestInvite('invitee@example.com');

    $response = $this->post(route('register.store', ['invite' => $invite->id]), [
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
});
