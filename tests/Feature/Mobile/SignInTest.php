<?php

declare(strict_types=1);

use App\Models\MobileToken;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'The Owner', 'email' => 'owner@example.com', 'password' => 'right-password']);
    Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('the owner signs in with their web email and password and gets a mobile token', function () {
    $response = $this->postJson(route('mobile.v1.sessions.store'), [
        'email' => 'owner@example.com',
        'password' => 'right-password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'The Owner')
        ->assertJsonStructure(['token', 'name']);

    $this->getJson(route('mobile.v1.me'), ['Authorization' => "Bearer {$response->json('token')}"])
        ->assertOk()
        ->assertExactJson(['name' => 'The Owner']);
});

test('only the hash of the mobile token is stored', function () {
    $token = $this->postJson(route('mobile.v1.sessions.store'), [
        'email' => 'owner@example.com',
        'password' => 'right-password',
    ])->json('token');

    $stored = MobileToken::query()->sole();

    expect($stored->user_id)->toBe($this->owner->id)
        ->and($stored->token_hash)->toBe(hash('sha256', $token))
        ->and($stored->token_hash)->not->toBe($token);
});

test('each sign in gets its own mobile token', function () {
    $credentials = ['email' => 'owner@example.com', 'password' => 'right-password'];

    $first = $this->postJson(route('mobile.v1.sessions.store'), $credentials)->json('token');
    $second = $this->postJson(route('mobile.v1.sessions.store'), $credentials)->json('token');

    expect($first)->not->toBe($second)
        ->and(MobileToken::query()->count())->toBe(2);
});

test('wrong credentials are refused on the email field without saying which was wrong', function (array $credentials) {
    $this->postJson(route('mobile.v1.sessions.store'), $credentials)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);

    expect(MobileToken::query()->count())->toBe(0);
})->with([
    'wrong password' => [['email' => 'owner@example.com', 'password' => 'wrong-password']],
    'unknown email' => [['email' => 'nobody@example.com', 'password' => 'right-password']],
]);

test('email and password are required', function () {
    $this->postJson(route('mobile.v1.sessions.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('sign in pauses after five wrong tries, as the web login does', function () {
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $try) {
        $this->postJson(route('mobile.v1.sessions.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    $response = $this->postJson(route('mobile.v1.sessions.store'), [
        'email' => 'owner@example.com',
        'password' => 'right-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($response->json('errors.email.0'))->toStartWith('Too many login attempts.');
    Event::assertDispatched(Lockout::class);
    expect(MobileToken::query()->count())->toBe(0);
});

test('a user who is not the owner cannot sign in', function () {
    User::factory()->create(['email' => 'later@example.com', 'password' => 'their-password']);

    $this->postJson(route('mobile.v1.sessions.store'), ['email' => 'later@example.com', 'password' => 'their-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);

    expect(MobileToken::query()->count())->toBe(0);
});

test('signing in keeps no session', function () {
    $this->postJson(route('mobile.v1.sessions.store'), ['email' => 'owner@example.com', 'password' => 'right-password'])
        ->assertCreated()
        ->assertCookieMissing(config('session.cookie'));

    $this->assertGuest();
});
