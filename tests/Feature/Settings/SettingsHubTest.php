<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;

test('settings hub requires authentication', function () {
    $this->get(route('app.settings'))->assertRedirect(route('login'));
});

test('the Owner sees the settings hub with only the profile card', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)->get(route('app.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->missing('permissions')
        );
});
