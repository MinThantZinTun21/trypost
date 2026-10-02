<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/invites/{invite}', [AcceptInviteController::class, 'show'])->name('app.invites.show');

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/invites/{invite}/accept', [AcceptInviteController::class, 'accept'])->name('app.invites.accept');
    Route::post('/invites/{invite}/decline', [AcceptInviteController::class, 'decline'])->name('app.invites.decline');
});
