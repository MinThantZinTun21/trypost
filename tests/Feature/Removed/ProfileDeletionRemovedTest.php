<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

test('the owner cannot delete their own account', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->json('DELETE', '/settings/profile', ['password' => 'password'])
        ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);

    expect(Route::has('app.profile.destroy'))->toBeFalse()
        ->and($owner->fresh())->not->toBeNull();
});

test('the delete account component and copy are gone', function () {
    expect(file_exists(resource_path('js/components/DeleteUser.vue')))->toBeFalse()
        ->and(trans('settings.delete_account'))->toBe('settings.delete_account');
});
