<?php

declare(strict_types=1);

use App\Jobs\PublishToSocialPlatform;
use Illuminate\Support\Facades\Schema;

test('horizon and predis are not composer dependencies', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $packages = array_keys(array_merge(data_get($composer, 'require', []), data_get($composer, 'require-dev', [])));

    expect($packages)->not->toContain('laravel/horizon')
        ->and($packages)->not->toContain('predis/predis');
});

test('no app config file defines a redis connection, queue or cache store', function (string $file) {
    expect(file_get_contents(config_path($file)))
        ->not->toContain("'driver' => 'redis'")
        ->not->toContain('REDIS_');
})->with(['database.php', 'queue.php', 'cache.php', 'session.php']);

test('horizon config is gone', function () {
    expect(file_exists(config_path('horizon.php')))->toBeFalse();
});

test('the queue and cache default to the database', function () {
    expect(file_get_contents(config_path('queue.php')))->toContain("env('QUEUE_CONNECTION', 'database')")
        ->and(file_get_contents(config_path('cache.php')))->toContain("env('CACHE_STORE', 'database')");
});

test('a slow publish is never handed to a second worker', function () {
    $publishTimeout = (new ReflectionClass(PublishToSocialPlatform::class))->getProperty('timeout')->getDefaultValue();

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($publishTimeout);
});

test('the queue and cache tables exist', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks']);

test('no compose file runs a redis or reverb service', function (string $path) {
    expect(strtolower(file_get_contents(base_path($path))))
        ->not->toContain('redis')
        ->not->toContain('reverb');
})->with(['compose.yaml', 'compose.prod.yaml', 'compose.override.yaml.example']);
