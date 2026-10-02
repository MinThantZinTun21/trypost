<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

test('billing, checkout and stripe endpoints are gone', function (string $method, string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)->json($method, $uri)->assertNotFound();
})->with([
    'subscribe' => ['GET', '/subscribe'],
    'billing processing' => ['GET', '/billing/processing'],
    'welcome plan' => ['GET', '/welcome/plan'],
    'welcome plan store' => ['POST', '/welcome/plan'],
    'welcome subscription required' => ['GET', '/welcome/subscription-required'],
    'account settings' => ['GET', '/settings/account'],
    'account settings update' => ['PUT', '/settings/account'],
    'billing' => ['GET', '/settings/account/billing'],
    'billing portal' => ['GET', '/settings/account/billing/portal'],
    'change plan' => ['POST', '/settings/account/billing/change-plan'],
    'stripe webhook' => ['POST', '/stripe/webhook'],
]);

test('an owner without any subscription reaches the calendar', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);
    $workspace->members()->attach($owner->id, ['role' => Role::Admin->value]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('auth.plan')
            ->missing('auth.hasActiveSubscription')
            ->missing('plans')
            ->missing('usage')
        );
});

test('laravel/cashier is not required by the app', function () {
    $require = json_decode((string) file_get_contents(base_path('composer.json')), true)['require'];

    expect($require)->not->toHaveKey('laravel/cashier')
        ->and(class_exists('Laravel\\Cashier\\Cashier'))->toBeFalse();
});
