<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\V1\MeController;
use App\Http\Controllers\Mobile\V1\PostController;
use App\Http\Controllers\Mobile\V1\VideoController;
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
        Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::put('posts/{post}/schedule', [PostController::class, 'reschedule'])->name('posts.reschedule');
        Route::post('posts/{post}/telegram', [PostController::class, 'sendToTelegram'])->name('posts.send');
        Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
        Route::post('videos/uploads', [VideoController::class, 'startUpload'])->name('videos.uploads.store');
        Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
        Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
    });
