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

test('the vultr app service runs the shipped image with the server env file', function () {
    $app = data_get(vultrComposeServices(), 'app');

    expect(data_get($app, 'image'))->toBe('trypost-app:latest')
        ->and(data_get($app, 'pull_policy'))->toBe('never')
        ->and(data_get($app, 'build'))->toBeNull()
        ->and(data_get($app, 'env_file'))->toBe('.env')
        ->and(data_get($app, 'restart'))->toBe('unless-stopped')
        ->and(data_get($app, 'environment.TRYPOST_TARGET'))->toBe('production')
        ->and(data_get($app, 'logging.driver'))->toBe('json-file')
        ->and(data_get($app, 'logging.options.max-size'))->not->toBeNull();
});

test('the app publishes no port, because chord book\'s caddy reaches it on the edge network', function () {
    $app = data_get(vultrComposeServices(), 'app');

    expect(data_get($app, 'ports'))->toBeNull()
        ->and(data_get($app, 'networks.edge.aliases'))->toContain('trypost')
        ->and(data_get(Yaml::parseFile(base_path('compose.vultr.yaml')), 'networks.edge.external'))->toBeTrue();
});

test('the app is capped to share the 1 GB server with chord book', function () {
    expect(data_get(vultrComposeServices(), 'app.mem_limit'))->toBe('512m');
});

test('the vultr server runs only the app, because postgres is supabase and caddy is chord book\'s', function () {
    expect(array_keys(vultrComposeServices()))->toBe(['app']);
});

test('the entrypoint skips waiting for a local postgres when DB_URL is set', function () {
    $entrypoint = (string) file_get_contents(base_path('docker/entrypoint.sh'));

    $skip = strpos($entrypoint, 'if [ -n "${DB_URL:-}" ]; then');

    expect($skip)->not->toBeFalse()
        ->and($skip)->toBeLessThan(strpos($entrypoint, 'pg_isready'));
});

/**
 * @return array<string, mixed>
 */
function vultrDeployWorkflow(): array
{
    return Yaml::parseFile(base_path('.github/workflows/deploy.yml'));
}

/**
 * Every `run:` script in the deploy job, joined.
 */
function vultrDeployScript(): string
{
    return collect(data_get(vultrDeployWorkflow(), 'jobs.deploy.steps'))->pluck('run')->filter()->implode("\n");
}

test('the deploy workflow only runs when the owner starts it', function () {
    expect(array_keys(data_get(vultrDeployWorkflow(), 'on')))->toBe(['workflow_dispatch'])
        ->and(data_get(vultrDeployWorkflow(), 'concurrency.cancel-in-progress'))->toBeFalse();
});

test('the deploy workflow builds the production image for the amd64 server', function () {
    expect(vultrDeployScript())
        ->toContain('--file docker/Dockerfile')
        ->toContain('--target production')
        ->toContain('--platform linux/amd64')
        ->toContain('--tag trypost-app:latest');
});

test('the deploy workflow ships the image over ssh and starts it with the vultr compose file', function () {
    expect(vultrDeployScript())
        ->toContain('docker save trypost-app:latest | gzip | ssh vultr')
        ->toContain('scp compose.vultr.yaml "vultr:')
        ->toContain('docker network inspect edge')
        ->toContain('docker compose -f compose.vultr.yaml up -d')
        ->not->toContain('Caddyfile')
        ->toContain('.State.Health.Status')
        ->toContain('https://${domain}/up');
});

test('the deploy workflow pins the server host key and reads its secrets', function () {
    $workflow = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($workflow)
        ->toContain('secrets.VULTR_HOST')
        ->toContain('secrets.VULTR_USER')
        ->toContain('secrets.VULTR_SSH_KEY')
        ->toContain('secrets.VULTR_KNOWN_HOSTS')
        ->and(vultrDeployScript())
        ->toContain('StrictHostKeyChecking yes')
        ->not->toContain('ssh-keyscan')
        ->not->toContain('${{');
});

test('the server setup script is strict and opens only ssh, http and https', function () {
    $script = (string) file_get_contents(base_path('docker/vultr-setup.sh'));

    expect($script)->toContain('set -euo pipefail')
        ->toContain('docker-compose-plugin')
        ->toContain('fallocate -l 2G /swapfile')
        ->toContain('ufw allow OpenSSH')
        ->toContain('ufw allow 80/tcp')
        ->toContain('ufw allow 443/tcp')
        ->toContain('unattended-upgrades')
        ->toContain('PasswordAuthentication no')
        ->toContain('/opt/trypost')
        ->and(is_executable(base_path('docker/vultr-setup.sh')))->toBeTrue();
});
