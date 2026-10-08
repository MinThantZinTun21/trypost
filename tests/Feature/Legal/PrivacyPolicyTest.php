<?php

declare(strict_types=1);

use App\Models\User;

test('guests can read the privacy policy', function () {
    config()->set('app.owner.email', 'owner@example.com');

    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('legal/Privacy')
            ->where('contactEmail', 'owner@example.com'));
});

test('a signed in owner can read the privacy policy too', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('legal/Privacy'));
});
