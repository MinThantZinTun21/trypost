<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

test('telemetry composer packages are not installed', function (string $package, string $class) {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->not->toHaveKey($package)
        ->and($composer['require-dev'])->not->toHaveKey($package)
        ->and(class_exists($class))->toBeFalse();
})->with([
    'posthog' => ['posthog/posthog-php', 'PostHog\\PostHog'],
    'nightwatch' => ['laravel/nightwatch', 'Laravel\\Nightwatch\\Facades\\Nightwatch'],
    'telescope' => ['laravel/telescope', 'Laravel\\Telescope\\Telescope'],
]);

test('posthog-js is not a frontend dependency', function () {
    $package = json_decode((string) file_get_contents(base_path('package.json')), true);

    expect($package['dependencies'] ?? [])->not->toHaveKey('posthog-js')
        ->and($package['devDependencies'] ?? [])->not->toHaveKey('posthog-js');
});

test('telemetry config is gone', function () {
    expect(config('services.posthog'))->toBeNull()
        ->and(config('services.gtm'))->toBeNull()
        ->and(config('telescope'))->toBeNull();
});

test('the telescope tables do not exist', function () {
    expect(Schema::hasTable('telescope_entries'))->toBeFalse()
        ->and(Schema::hasTable('telescope_entries_tags'))->toBeFalse()
        ->and(Schema::hasTable('telescope_monitoring'))->toBeFalse();
});

test('the guest app layout renders no tracking snippet', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('googletagmanager')
        ->not->toContain('dataLayer')
        ->not->toContain('posthog');
});

test('the authenticated app layout renders no tracking snippet or tracking props', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);
    $workspace->members()->attach($owner->id, ['role' => Role::Admin->value]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($owner)->get(route('app.calendar'))->assertOk();

    expect($response->getContent())
        ->not->toContain('googletagmanager')
        ->not->toContain('dataLayer')
        ->not->toContain('posthog');

    $response->assertInertia(fn ($page) => $page
        ->missing('applicationUrl')
        ->missing('env')
    );
});
