<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\V1\MeController;
use App\Http\Controllers\Mobile\V1\PostController;
use App\Http\Controllers\Mobile\V1\SessionController;
use App\Http\Controllers\Mobile\V1\SocialAccountController;
use App\Http\Controllers\Mobile\V1\TagGroupController;
use App\Http\Controllers\Mobile\V1\TaskController;
use App\Http\Controllers\Mobile\V1\TaskTagController;
use App\Http\Controllers\Mobile\V1\VideoController;
use App\Http\Middleware\AuthenticateMobileToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
 * The Owner's Android app (ADR 0010). Outside the web group on purpose: no
 * session, cookies or CSRF. The phone signs in with the Owner's web email and
 * password and every other call carries the Mobile token it got (ADR 0015).
 */
Route::prefix('mobile/v1')
    ->name('mobile.v1.')
    ->group(function (): void {
        Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');

        Route::middleware([AuthenticateMobileToken::class, SubstituteBindings::class])->group(function (): void {
            Route::delete('session', [SessionController::class, 'destroy'])->name('session.destroy');
            Route::get('me', MeController::class)->name('me');
            Route::get('posts', [PostController::class, 'index'])->name('posts.index');
            Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
            Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
            Route::put('posts/{post}/schedule', [PostController::class, 'reschedule'])->name('posts.reschedule');
            Route::post('posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
            Route::post('posts/{post}/telegram', [PostController::class, 'sendToTelegram'])->name('posts.send');
            Route::get('social-accounts', SocialAccountController::class)->name('social-accounts.index');
            Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
            Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::put('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::get('tag-groups', [TagGroupController::class, 'index'])->name('tag-groups.index');
            Route::post('tag-groups', [TagGroupController::class, 'store'])->name('tag-groups.store');
            Route::put('tag-groups/{tagGroup}', [TagGroupController::class, 'update'])->name('tag-groups.update');
            Route::delete('tag-groups/{tagGroup}', [TagGroupController::class, 'destroy'])->name('tag-groups.destroy');
            Route::post('tag-groups/{tagGroup}/task-tags', [TaskTagController::class, 'store'])->name('task-tags.store');
            Route::put('task-tags/{taskTag}', [TaskTagController::class, 'update'])->name('task-tags.update');
            Route::delete('task-tags/{taskTag}', [TaskTagController::class, 'destroy'])->name('task-tags.destroy');
            Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
            Route::post('videos/uploads', [VideoController::class, 'startUpload'])->name('videos.uploads.store');
            Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
            Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
        });
    });
