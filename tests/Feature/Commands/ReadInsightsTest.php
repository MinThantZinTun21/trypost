<?php

declare(strict_types=1);

use App\Jobs\ReadFacebookInsights;
use App\Models\SocialAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

test('it queues a read for each connected Facebook Page only', function () {
    Queue::fake();

    $connected = SocialAccount::factory()->facebook()->create();
    SocialAccount::factory()->facebook()->disconnected()->create();
    SocialAccount::factory()->tiktok()->create();
    SocialAccount::factory()->youtube()->create();

    $this->artisan('insights:read')->assertSuccessful();

    Queue::assertPushed(ReadFacebookInsights::class, 1);
    Queue::assertPushed(ReadFacebookInsights::class, fn (ReadFacebookInsights $job): bool => $job->account->is($connected));
});

test('the schedule reads Insights daily at 03:00 UTC', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'insights:read');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *');
});
