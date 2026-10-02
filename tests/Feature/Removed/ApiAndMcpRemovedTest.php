<?php

declare(strict_types=1);

use App\Models\User;

test('rest api, oauth and mcp endpoints are gone', function (string $method, string $uri) {
    $this->json($method, $uri)->assertNotFound();
})->with([
    'api posts' => ['GET', '/api/posts'],
    'api keys' => ['GET', '/api/api-keys'],
    'signed uploads' => ['POST', '/api/uploads/'.fake()->uuid()],
    'mcp server' => ['POST', '/mcp/trypost'],
    'oauth authorize' => ['GET', '/oauth/authorize'],
    'oauth token' => ['POST', '/oauth/token'],
]);

test('api key and mcp settings pages are gone', function (string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($uri)->assertNotFound();
})->with([
    '/settings/workspace/api-keys',
    '/settings/workspace/mcp',
]);

test('passport and laravel/mcp are not required by the app', function () {
    $require = json_decode((string) file_get_contents(base_path('composer.json')), true)['require'];

    expect($require)->not->toHaveKeys(['laravel/passport', 'laravel/mcp'])
        ->and(class_exists('Laravel\\Passport\\Passport'))->toBeFalse();
});
