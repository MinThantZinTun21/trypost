<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(fn () => config([
    'trypost.google_auth_enabled' => true,
    'trypost.github_auth_enabled' => true,
]));

test('existing google user login consumes the click id session so it does not leak to a later signup', function () {
    User::factory()->create([
        'email' => 'existing-click@example.com',
        'google_id' => 'g-existing-click',
    ]);

    $this->get(route('auth.google.redirect', ['gclid' => 'stale-click']));

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'g-existing-click';
    $socialiteUser->name = 'Existing Click User';
    $socialiteUser->email = 'existing-click@example.com';

    Socialite::shouldReceive('driver')
        ->with('google-auth')
        ->andReturn($driver = Mockery::mock());

    $driver->shouldReceive('user')
        ->andReturn($socialiteUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('app.home'));

    expect(session()->get('attribution_parameters'))->toBeNull();
});

test('existing github user login consumes the click id session so it does not leak to a later signup', function () {
    User::factory()->create([
        'email' => 'existing-gh-click@example.com',
        'github_id' => 'gh-existing-click',
    ]);

    $this->get(route('auth.github.redirect', ['gclid' => 'stale-gh-click']));

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'gh-existing-click';
    $socialiteUser->name = 'Existing GitHub Click User';
    $socialiteUser->email = 'existing-gh-click@example.com';

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
