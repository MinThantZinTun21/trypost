<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * The app runs on a Vultr server with a real queue worker and scheduler
 * (ADR 0005), so the Vercel deploy and its cron endpoint are gone.
 */
test('the cron endpoint is gone', function (array $headers, string $query) {
    config()->set('trypost.cron.secret', 'cron-test-secret');

    $this->getJson("/cron/run{$query}", $headers)->assertNotFound();
})->with([
    'bearer secret' => [['Authorization' => 'Bearer cron-test-secret'], ''],
    'query token' => [[], '?token=cron-test-secret'],
]);

test('the cron token command is gone', function () {
    expect(Artisan::all())->not->toHaveKey('cron:token');
});

test('the vercel deploy files are gone', function (string $path) {
    expect(file_exists(base_path($path)))->toBeFalse();
})->with(['vercel.json', '.vercelignore', 'api/index.php', 'routes/cron.php']);

test('there is no cron endpoint config', function () {
    $trypost = require config_path('trypost.php');

    expect($trypost)->not->toHaveKey('cron')
        ->and(file_get_contents(base_path('.env.example')))->not->toContain('CRON_SECRET');
});
