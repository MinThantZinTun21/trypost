<?php

declare(strict_types=1);

use App\Support\CronToken;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config()->set('trypost.cron.secret', 'cron-test-secret');
});

test('it issues a token that works for the requested number of days', function () {
    $this->freezeTime();

    Artisan::call('cron:token', ['--days' => 30]);
    $token = trim(strtok(Artisan::output(), "\n"));

    expect(CronToken::accepts('cron-test-secret', $token))->toBeTrue();

    $this->travel(30)->days();
    $this->travel(-1)->seconds();
    expect(CronToken::accepts('cron-test-secret', $token))->toBeTrue();

    $this->travel(1)->seconds();
    expect(CronToken::accepts('cron-test-secret', $token))->toBeFalse();
});

test('it defaults to thirty days', function () {
    $this->freezeTime();

    Artisan::call('cron:token');
    $expires = (int) strtok(Artisan::output(), '.');

    expect($expires)->toBe(now()->addDays(30)->getTimestamp());
});

test('it refuses without a secret or with a bad day count', function (?string $secret, string $days) {
    config()->set('trypost.cron.secret', $secret);

    $this->artisan('cron:token', ['--days' => $days])->assertFailed();
})->with([
    'no secret' => [null, '30'],
    'zero days' => ['cron-test-secret', '0'],
    'not a number' => ['cron-test-secret', 'month'],
]);
