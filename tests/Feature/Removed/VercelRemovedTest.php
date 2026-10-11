<?php

declare(strict_types=1);

/**
 * The app runs on a Vultr server with a real queue worker and scheduler
 * (ADR 0005), so the Vercel deploy is gone. GET /cron/run came back for the
 * Owner's external cron (tests/Feature/Cron), next to schedule:work.
 */
test('the vercel deploy files are gone', function (string $path) {
    expect(file_exists(base_path($path)))->toBeFalse();
})->with(['vercel.json', '.vercelignore', 'api/index.php']);
