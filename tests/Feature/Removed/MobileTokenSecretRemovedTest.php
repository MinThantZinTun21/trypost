<?php

declare(strict_types=1);

/**
 * The Mobile app signs in with the Owner's web password and gets a Mobile
 * token per phone (ADR 0015), so the shared MOBILE_TOKEN secret is gone.
 */
test('the MOBILE_TOKEN secret and its config are gone', function () {
    expect(config('trypost.mobile'))->toBeNull()
        ->and(file_get_contents(base_path('.env.example')))->not->toContain('MOBILE_TOKEN')
        ->and(file_get_contents(base_path('config/trypost.php')))->not->toContain('MOBILE_TOKEN');
});
