<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('signup, onboarding, social login, email verification and password reset routes are gone', function (string $method, string $uri) {
    $this->json($method, $uri)->assertNotFound();
})->with([
    'register' => ['GET', '/register'],
    'register store' => ['POST', '/register'],
    'forgot password' => ['GET', '/forgot-password'],
    'forgot password store' => ['POST', '/forgot-password'],
    'reset password' => ['GET', '/reset-password/some-token'],
    'reset password store' => ['POST', '/reset-password'],
    'google redirect' => ['GET', '/auth/google/redirect'],
    'google callback' => ['GET', '/auth/google/callback'],
    'github redirect' => ['GET', '/auth/github/redirect'],
    'github callback' => ['GET', '/auth/github/callback'],
]);

test('authenticated-only signup leftovers are gone', function (string $method, string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)->json($method, $uri)->assertNotFound();
})->with([
    'welcome' => ['GET', '/welcome'],
    'welcome persona' => ['GET', '/welcome/persona'],
    'welcome goals' => ['GET', '/welcome/goals'],
    'welcome referral source' => ['GET', '/welcome/referral-source'],
    'welcome connect' => ['GET', '/welcome/connect'],
    'welcome connect store' => ['POST', '/welcome/connect'],
    'verify email notice' => ['GET', '/verify-email'],
    'verify email link' => ['GET', '/verify-email/some-id/some-hash'],
    'verification notification' => ['POST', '/email/verification-notification'],
    'login provider connect' => ['GET', '/settings/authentication/providers/google/connect'],
    'login provider disconnect' => ['DELETE', '/settings/authentication/providers/google'],
]);

test('the login page shows only the form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->missing('legal')
            ->missing('googleAuthEnabled')
            ->missing('githubAuthEnabled')
        );
});

test('the seeded owner can log in and reach the calendar', function () {
    config()->set('app.owner', [
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'owner-secret',
    ]);

    $this->seed(DatabaseSeeder::class);

    $this->post(route('login.store'), [
        'email' => 'owner@example.com',
        'password' => 'owner-secret',
    ])->assertRedirect(route('app.calendar'));

    $this->assertAuthenticatedAs(User::sole());

    $this->get(route('app.calendar'))->assertOk();
});
