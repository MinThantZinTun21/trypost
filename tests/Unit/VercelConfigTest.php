<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function vercelFunctionConfig(): array
{
    $config = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/vercel.json'), true);

    return data_get($config, 'functions')['api/index.php'];
}

test('the vercel function outlives the cron queue budget', function () {
    $trypost = require dirname(__DIR__, 2).'/config/trypost.php';

    expect(vercelFunctionConfig()['maxDuration'])->toBeGreaterThan(data_get($trypost, 'cron.max_seconds'));
});

test('the vercel bundle keeps the youtube and s3 sdk code the app uses', function () {
    $excluded = vercelFunctionConfig()['excludeFiles'];

    expect($excluded)->toBeString()
        ->and(strlen($excluded))->toBeLessThanOrEqual(256)
        ->and($excluded)
        ->toContain('apiclient-services/src/!(YouTube)/**')
        ->toContain('aws-sdk-php/src/data/!(s3)/**')
        ->not->toContain('vendor/google/apiclient/')
        ->not->toContain('vendor/aws/aws-sdk-php/src/S3');
});

test('the vercel entry point keeps laravel writes inside tmp', function () {
    $entry = (string) file_get_contents(dirname(__DIR__, 2).'/api/index.php');

    expect($entry)->toContain("ini_set('display_errors', '0');")
        ->toContain("'LARAVEL_STORAGE_PATH' => '/tmp/storage'")
        ->toContain("'APP_PACKAGES_CACHE' => '/tmp/bootstrap/packages.php'")
        ->toContain("require __DIR__.'/../public/index.php';");
});

test('vercel serves static files from a copy of public without the php front controller', function () {
    $config = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/vercel.json'), true);

    expect(data_get($config, 'outputDirectory'))->not->toBe('public')
        ->and(data_get($config, 'buildCommand'))
        ->toContain('cp -R public/. '.data_get($config, 'outputDirectory'))
        ->toContain('rm -f '.data_get($config, 'outputDirectory').'/index.php');
});

test('vercel never builds from git pushes, because the frontend is built before a cli deploy', function () {
    $config = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/vercel.json'), true);

    expect(data_get($config, 'git.deploymentEnabled'))->toBeFalse()
        ->and(data_get($config, 'buildCommand'))->toContain('public/build/manifest.json');
});
