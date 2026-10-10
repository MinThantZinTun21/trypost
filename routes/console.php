<?php

declare(strict_types=1);

use App\Console\Commands\CheckSocialConnections;
use App\Console\Commands\CheckUpcomingPostConnections;
use App\Console\Commands\ProcessScheduledPosts;
use App\Console\Commands\ReadInsights;
use App\Console\Commands\RecoverStuckPosts;
use App\Console\Commands\RefreshExpiringTokens;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Callbacks, not Schedule::command(): they run inside the schedule:run
 * process, so GET /cron/run can drive the same schedule from a web request
 * on serverless hosts, where spawning `php artisan` subprocesses is fragile.
 */
Schedule::call(fn () => Artisan::call(ProcessScheduledPosts::class))
    ->name('posts:process-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => Artisan::call(CheckSocialConnections::class))
    ->name('social:check-connections')->daily()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => Artisan::call(CheckUpcomingPostConnections::class))
    ->name('social:check-upcoming-connections')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => Artisan::call(RefreshExpiringTokens::class))
    ->name('social:refresh-expiring-tokens')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => Artisan::call(RecoverStuckPosts::class))
    ->name('social:recover-stuck-posts')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::call(fn () => Artisan::call(ReadInsights::class))
    ->name('insights:read')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
