<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('app.profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->put(route('app.profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->put(route('app.profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can upload profile photo', function () {
    Storage::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('app.profile.upload-photo'), [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->has_photo)->toBeTrue();
    expect($user->photo_url)->not->toBeNull();
});

test('user cannot upload non-image file as photo', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('app.profile.upload-photo'), [
            'photo' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

    $response->assertSessionHasErrors('photo');
});

test('user cannot upload a photo over the size limit', function () {
    Storage::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('app.profile.upload-photo'), [
            'photo' => UploadedFile::fake()->image('avatar.jpg')->size(2049),
        ])
        ->assertSessionHasErrors('photo');

    expect($user->refresh()->has_photo)->toBeFalse();
});

test('user can delete profile photo', function () {
    Storage::fake();

    $user = User::factory()->create();

    // Upload first
    $this->actingAs($user)->post(route('app.profile.upload-photo'), [
        'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ]);

    $user->refresh();
    expect($user->has_photo)->toBeTrue();

    // Delete
    $response = $this->actingAs($user)->delete(route('app.profile.delete-photo'));

    $response->assertRedirect();

    $user->refresh();
    expect($user->has_photo)->toBeFalse();
});

test('user cannot upload photo exceeding max size', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('app.profile.upload-photo'), [
            'photo' => UploadedFile::fake()->image('large.jpg')->size(6000),
        ]);

    $response->assertSessionHasErrors('photo');
});
