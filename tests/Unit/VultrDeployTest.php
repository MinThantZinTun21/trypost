<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function vultrComposeServices(): array
{
    return data_get(Yaml::parseFile(base_path('compose.vultr.yaml')), 'services');
}

test('the vultr app service runs the production image with the server env file', function () {
    $app = data_get(vultrComposeServices(), 'app');

    expect(data_get($app, 'build.target'))->toBe('production')
        ->and(data_get($app, 'build.dockerfile'))->toBe('docker/Dockerfile')
        ->and(data_get($app, 'image'))->toBe('trypost-app:latest')
        ->and(data_get($app, 'env_file'))->toBe('.env')
        ->and(data_get($app, 'restart'))->toBe('unless-stopped')
        ->and(data_get($app, 'environment.TRYPOST_TARGET'))->toBe('production')
        ->and(data_get($app, 'logging.driver'))->toBe('json-file')
        ->and(data_get($app, 'logging.options.max-size'))->not->toBeNull();
});

test('only caddy is published, because the app trusts every proxy', function () {
    $services = vultrComposeServices();

    expect(data_get($services, 'app.ports'))->toBeNull()
        ->and(data_get($services, 'caddy.ports'))->toContain('80:80', '443:443')
        ->and(data_get($services, 'caddy.environment.APP_DOMAIN'))->toContain('APP_DOMAIN')
        ->and(data_get($services, 'caddy.volumes'))->toContain('./Caddyfile:/etc/caddy/Caddyfile:ro');
});

test('the vultr server has no database service, because postgres is supabase', function () {
    expect(array_keys(vultrComposeServices()))->toEqualCanonicalizing(['app', 'caddy']);
});

test('the entrypoint skips waiting for a local postgres when DB_URL is set', function () {
    $entrypoint = (string) file_get_contents(base_path('docker/entrypoint.sh'));

    $skip = strpos($entrypoint, 'if [ -n "${DB_URL:-}" ]; then');

    expect($skip)->not->toBeFalse()
        ->and($skip)->toBeLessThan(strpos($entrypoint, 'pg_isready'));
});
