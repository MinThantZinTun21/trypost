<?php

declare(strict_types=1);

use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\TermsOfServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/privacy', PrivacyPolicyController::class)->name('privacy');
Route::get('/terms', TermsOfServiceController::class)->name('terms');

require __DIR__.'/auth.php';
require __DIR__.'/app.php';
