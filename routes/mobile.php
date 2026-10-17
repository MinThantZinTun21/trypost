<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\V1\MeController;
use App\Http\Controllers\Mobile\V1\PostController;
use App\Http\Middleware\AuthenticateMobileToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
 * The Owner's Android app (ADR 0010). Outside the web group on purpose: no
 * session, cookies or CSRF; one bearer secret, MOBILE_TOKEN, acts as the Owner.
 */
Route::prefix('mobile/v1')
    ->name('mobile.v1.')
    ->middleware([AuthenticateMobileToken::class, SubstituteBindings::class])
    ->group(function (): void {
        Route::get('me', MeController::class)->name('me');
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    });
