<?php

declare(strict_types=1);

use App\Http\Controllers\Cron\RunScheduleController;
use Illuminate\Support\Facades\Route;

/*
 * Outside the web middleware group on purpose: no session, cookies or CSRF,
 * so an external cron service calling every minute leaves no session rows.
 */
Route::get('cron/run', RunScheduleController::class)->name('cron.run');
