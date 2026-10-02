<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(fn () => config([
    'trypost.google_auth_enabled' => true,
    'trypost.github_auth_enabled' => true,
]));

test('existing google user login consumes the utm session so utms do not leak to a later signup', function () {
    User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => 'g-existing',
    ]);

    $this->get(route('auth.google.redirect', ['utm_source' => 'twitter']));

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'g-existing';
    $socialiteUser->name = 'Existing User';
    $socialiteUser->email = 'existing@example.com';

    Socialite::shouldReceive('driver')
        ->with('google-auth')
        ->andReturn($driver = Mockery::mock());

    $driver->shouldReceive('user')
        ->andReturn($socialiteUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('app.home'));

    expect(session()->get('attribution_parameters'))->toBeNull();
});

test('invitation registration redirects to the invite page instead of app.welcome', function () {
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
        'email' => 'invited@example.com',
        'workspaces' => [$workspace->id],
    ]);

    $this->get(route('register', ['utm_source' => 'email', 'invite' => $invite->id]));

    $this->post(route('register.store'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'Password123!',
        'invite' => $invite->id,
    ])
        ->assertRedirect(route('app.invites.show', $invite));
});

test('github registration without email redirects to login with error', function () {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-no-email';
    $socialiteUser->name = 'No Email';
    $socialiteUser->email = null;

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn($driver = Mockery::mock());

    $driver->shouldReceive('scopes')
        ->andReturnSelf();

    $driver->shouldReceive('user')
        ->andReturn($socialiteUser);

    $this->get(route('auth.github.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('existing github user login consumes the utm session and skips signup success', function () {
    User::factory()->create([
        'email' => 'existing-gh@example.com',
        'github_id' => 'gh-existing',
    ]);

    $this->get(route('auth.github.redirect', ['utm_source' => 'hackernews']));

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-existing';
    $socialiteUser->name = 'Existing GitHub';
    $socialiteUser->email = 'existing-gh@example.com';

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn($driver = Mockery::mock());

    $driver->shouldReceive('scopes')
        ->andReturnSelf();

    $driver->shouldReceive('user')
        ->andReturn($socialiteUser);

    $this->get(route('auth.github.callback'))
        ->assertRedirect(route('app.home'));

    expect(session()->get('attribution_parameters'))->toBeNull();
});
