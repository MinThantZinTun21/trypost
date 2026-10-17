<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function androidReleaseWorkflow(): array
{
    return Yaml::parseFile(base_path('.github/workflows/android-release.yml'));
}

/**
 * Every `run:` script in the release job, joined.
 */
function androidReleaseScript(): string
{
    return collect(data_get(androidReleaseWorkflow(), 'jobs.release.steps'))->pluck('run')->filter()->implode("\n");
}

test('the release workflow only runs when the owner starts it', function () {
    expect(array_keys(data_get(androidReleaseWorkflow(), 'on')))->toBe(['workflow_dispatch']);
});

test('the release workflow signs with the keystore from secrets and never echoes it', function () {
    $workflow = (string) file_get_contents(base_path('.github/workflows/android-release.yml'));

    expect($workflow)
        ->toContain('secrets.ANDROID_KEYSTORE_BASE64')
        ->toContain('secrets.ANDROID_KEYSTORE_PASSWORD')
        ->toContain('secrets.ANDROID_KEY_ALIAS')
        ->toContain('secrets.ANDROID_KEY_PASSWORD')
        ->and(androidReleaseScript())
        ->toContain('base64 --decode')
        ->toContain(':app:assembleRelease')
        ->toContain('apksigner verify')
        ->toContain('rm -f "$TRYPOST_KEYSTORE_FILE"')
        ->not->toContain('${{')
        ->not->toContain('set -x');
});

test('the release workflow uploads the signed apk', function () {
    $upload = collect(data_get(androidReleaseWorkflow(), 'jobs.release.steps'))
        ->first(fn (array $step): bool => str_starts_with((string) data_get($step, 'uses'), 'actions/upload-artifact@'));

    expect(data_get($upload, 'with.path'))->toContain('android/app/build/outputs/apk/release/')
        ->and(data_get($upload, 'with.if-no-files-found'))->toBe('error');
});

test('the gradle release build reads the same signing settings locally and in ci', function () {
    $gradle = (string) file_get_contents(base_path('android/app/build.gradle.kts'));
    $ignored = (string) file_get_contents(base_path('android/.gitignore'));

    expect($gradle)
        ->toContain('keystore.properties')
        ->toContain('TRYPOST_KEYSTORE_FILE')
        ->toContain('TRYPOST_KEYSTORE_PASSWORD')
        ->toContain('TRYPOST_KEY_ALIAS')
        ->toContain('TRYPOST_KEY_PASSWORD')
        ->toContain('TRYPOST_VERSION_CODE')
        ->and($ignored)
        ->toContain('keystore.properties')
        ->toContain('*.jks');
});
