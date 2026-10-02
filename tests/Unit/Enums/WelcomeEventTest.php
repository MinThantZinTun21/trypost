<?php

declare(strict_types=1);

use App\Enums\PostHog\WelcomeEvent;

test('welcome funnel puts connect after referral', function () {
    expect(WelcomeEvent::funnel())->toBe([
        WelcomeEvent::Persona->value,
        WelcomeEvent::Goals->value,
        WelcomeEvent::Referral->value,
        WelcomeEvent::Connect->value,
    ]);
});
