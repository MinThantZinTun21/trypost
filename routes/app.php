<?php

declare(strict_types=1);

use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\ContentIdeaController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\PostController;
use App\Http\Controllers\App\Settings\AuthenticationController;
use App\Http\Controllers\App\Settings\ProfileController;
use App\Http\Controllers\App\Settings\SettingsController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\YouTubeController;
use App\Http\Middleware\App\EnsureHasWorkspace;
use Illuminate\Support\Facades\Route;

// Home (auth only)
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('app.calendar');
    })->name('app.home');
});

// Social Connect routes
Route::middleware(['auth'])->group(function () {
    // Starting a connection reads the user's current workspace, so these require
    // one — during onboarding they redirect to workspace creation. The
    // disconnect controller still authorizes workspace ownership.
    Route::middleware(EnsureHasWorkspace::class)->group(function () {
        Route::get('connect/tiktok', [TikTokController::class, 'connect'])->name('app.social.tiktok.connect');
        Route::get('connect/youtube', [YouTubeController::class, 'connect'])->name('app.social.youtube.connect');
        Route::get('connect/facebook', [FacebookController::class, 'connect'])->name('app.social.facebook.connect');

        Route::delete('accounts/{account}', [SocialController::class, 'disconnect'])->name('app.accounts.disconnect');
    });

    // OAuth callbacks and identity selection resolve their workspace from the
    // session set when the flow started, then self-close the popup. They run
    // without the current-workspace gate so a momentarily missing current
    // workspace can't HTML-redirect the popup instead of closing it cleanly.
    Route::get('accounts/tiktok/callback', [TikTokController::class, 'callback'])->name('app.social.tiktok.callback');

    Route::get('accounts/youtube/callback', [YouTubeController::class, 'callback'])->name('app.social.youtube.callback');

    Route::get('accounts/facebook/callback', [FacebookController::class, 'callback'])->name('app.social.facebook.callback');
    Route::get('accounts/facebook/select', [FacebookController::class, 'selectPage'])->name('app.social.facebook.select-page');
    Route::post('accounts/facebook/select', [FacebookController::class, 'select'])->name('app.social.facebook.select');
});

// Routes that require a current workspace
Route::middleware(['auth', EnsureHasWorkspace::class])->group(function () {
    // Social Accounts
    Route::get('accounts', [SocialController::class, 'index'])->name('app.accounts');
    Route::put('accounts/{account}/toggle', [SocialController::class, 'toggleActive'])->name('app.accounts.toggle');

    // Calendar
    Route::get('calendar', [PostController::class, 'calendar'])->name('app.calendar');

    // Posts
    Route::get('posts/{status?}', [PostController::class, 'index'])->name('app.posts.index')->where('status', 'draft|scheduled|published');
    Route::post('posts', [PostController::class, 'store'])->name('app.posts.store');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('app.posts.edit');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('app.posts.show');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('app.posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('app.posts.destroy');
    Route::post('posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('app.posts.duplicate');

    // Content ideas
    Route::get('ideas/{status?}', [ContentIdeaController::class, 'index'])->name('app.ideas.index')->where('status', 'new|in_progress|done');
    Route::post('ideas', [ContentIdeaController::class, 'store'])->name('app.ideas.store');

    // Media uploads (chunked, from the post composer)
    Route::post('assets/chunked', [AssetController::class, 'storeChunked'])->name('app.assets.store-chunked');

    // Media uploads straight to object storage (presigned PUT), bypassing request body limits
    Route::post('assets/direct', [AssetController::class, 'storeDirect'])->name('app.assets.store-direct');
    Route::post('assets/direct/complete', [AssetController::class, 'completeDirect'])->name('app.assets.complete-direct');
});

// Notifications (auth only)
Route::middleware(['auth'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('app.notifications.index');
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('app.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('app.notifications.read-all');
    Route::post('notifications/archive-all', [NotificationController::class, 'archiveAll'])->name('app.notifications.archive-all');
});

// Settings (auth required)
Route::middleware(['auth'])->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('app.settings');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('app.profile.edit');
    Route::put('settings/profile', [ProfileController::class, 'update'])->name('app.profile.update');
    Route::post('settings/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('app.profile.upload-photo');
    Route::delete('settings/profile/photo', [ProfileController::class, 'deletePhoto'])->name('app.profile.delete-photo');
});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/authentication', [AuthenticationController::class, 'edit'])->name('app.authentication.edit');
    Route::put('settings/authentication/password', [AuthenticationController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('app.authentication.update-password');
    Route::delete('settings/authentication/sessions', [AuthenticationController::class, 'destroyOtherSessions'])
        ->name('app.authentication.destroy-other-sessions');

});
