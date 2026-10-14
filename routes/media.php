<?php

declare(strict_types=1);

use App\Http\Controllers\DownloadMediaController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

/*
 * The Download link in a Telegram mirror (ADR 0008). Signed and permanent,
 * with no sign-in, so anyone in the channel can download the video. Outside
 * the web middleware group: no session or cookies for a plain file download.
 */
Route::get('media/{media}/download', DownloadMediaController::class)
    ->middleware([ValidateSignature::class, SubstituteBindings::class])
    ->name('media.download');
