<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PrivacyPolicyController extends Controller
{
    /**
     * Public privacy policy, linked from the Meta, TikTok and Google app settings.
     */
    public function __invoke(): Response
    {
        return Inertia::render('legal/Privacy', [
            'contactEmail' => config('app.owner.email'),
        ]);
    }
}
