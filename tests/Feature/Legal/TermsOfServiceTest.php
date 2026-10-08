<?php

declare(strict_types=1);

use App\Models\User;

test('guests can read the terms of service', function () {
    config()->set('app.owner.email', 'owner@example.com');

    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/Terms')
            ->where('contactEmail', 'owner@example.com'));
});

test('a signed in owner can read the terms of service too', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('legal/Terms'));
});
