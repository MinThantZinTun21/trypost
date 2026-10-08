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

/**
 * laravel/mcp came back for the Assistant's server at POST /mcp (ADR 0003);
 * the old Passport-backed server at /mcp/trypost stays gone.
 */
test('passport is not required by the app', function () {
    $require = json_decode((string) file_get_contents(base_path('composer.json')), true)['require'];

    expect($require)->not->toHaveKey('laravel/passport')
        ->and(class_exists('Laravel\\Passport\\Passport'))->toBeFalse();
});

test('no ci workflow or action runs a passport command', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('.github'), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        expect(file_get_contents($file->getPathname()))->not->toContain('passport:');
    }
});
