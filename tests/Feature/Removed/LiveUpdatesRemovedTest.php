<?php

declare(strict_types=1);

use App\Models\User;

test('broadcast auth and presence endpoints are gone', function (string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson($uri)->assertNotFound();
})->with(['/broadcasting/auth', '/presence/heartbeat']);

test('reverb is not a composer dependency', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect(array_keys(data_get($composer, 'require', [])))->not->toContain('laravel/reverb');
});

test('echo and pusher are not npm dependencies', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);
    $dependencies = array_keys(array_merge(data_get($package, 'dependencies', []), data_get($package, 'devDependencies', [])));

    expect($dependencies)->not->toContain('laravel-echo')
        ->and($dependencies)->not->toContain('@laravel/echo-vue')
        ->and($dependencies)->not->toContain('pusher-js');
});

test('the app ships no broadcasting config or channels', function () {
    expect(file_exists(config_path('broadcasting.php')))->toBeFalse()
        ->and(file_exists(config_path('reverb.php')))->toBeFalse()
        ->and(file_exists(base_path('routes/channels.php')))->toBeFalse()
        ->and(is_dir(app_path('Events')))->toBeFalse();
});
