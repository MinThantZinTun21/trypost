<?php

declare(strict_types=1);

use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\ReferralSource;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('welcome redirects to the persona step', function () {
    $this->actingAs($this->user)
        ->get(route('app.welcome'))
        ->assertRedirect(route('app.welcome.persona'));
});

test('welcome steps redirect owners to the calendar', function (string $routeName, string $method, array $payload = []) {
    $this->actingAs($this->user);

    $response = $method === 'get'
        ? $this->get(route($routeName))
        : $this->post(route($routeName), $payload);

    $response->assertRedirect(route('app.calendar'));
})->with([
    'persona' => ['app.welcome.persona', 'get'],
    'persona store' => ['app.welcome.persona.store', 'post', ['persona' => Persona::Agency->value]],
    'goals' => ['app.welcome.goals', 'get'],
    'goals store' => ['app.welcome.goals.store', 'post', ['goals' => [Goal::SaveTime->value]]],
    'referral source' => ['app.welcome.referral-source', 'get'],
    'referral source store' => ['app.welcome.referral-source.store', 'post', ['referral_source' => ReferralSource::Google->value]],
    'connect' => ['app.welcome.connect', 'get'],
    'connect store' => ['app.welcome.connect.store', 'post'],
]);

test('welcome sends members to the calendar', function () {
    ['member' => $member] = strandedMemberOnSharedAccount();

    $this->actingAs($member)
        ->get(route('app.welcome.persona'))
        ->assertRedirect(route('app.calendar'));
});

test('billing and old onboarding routes are not registered', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'welcome plan' => 'app.welcome.plan',
    'welcome plan store' => 'app.welcome.plan.store',
    'welcome subscription required' => 'app.welcome.subscription-required',
    'index' => 'app.onboarding',
    'store' => 'app.onboarding.store',
    'skip mcp' => 'app.onboarding.mcp.skip',
    'complete' => 'app.onboarding.complete',
    'goals' => 'app.onboarding.goals',
    'goals store' => 'app.onboarding.goals.store',
    'referral source' => 'app.onboarding.referral-source',
    'referral source store' => 'app.onboarding.referral-source.store',
    'connect' => 'app.onboarding.connect',
    'checkout' => 'app.onboarding.checkout',
]);
