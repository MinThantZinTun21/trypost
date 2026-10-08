<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TermsOfServiceController extends Controller
{
    /**
     * Public terms of service, linked from the TikTok, Meta and Google app settings.
     */
    public function __invoke(): Response
    {
        return Inertia::render('legal/Terms', [
            'contactEmail' => config('app.owner.email'),
        ]);
    }
}
