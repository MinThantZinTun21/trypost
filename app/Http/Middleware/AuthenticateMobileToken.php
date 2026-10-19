<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MobileToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Mobile app's API with the Mobile token the phone got when the
 * Owner signed in with their web password (ADR 0015). A missing or unknown
 * token is 401, which sends the app back to Sign in. A known one acts as its
 * Owner in their workspace, resolved up front because Post policies check it.
 */
class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = (string) $request->bearerToken();
        $mobileToken = $plainToken === '' ? null : MobileToken::findByPlainToken($plainToken);

        abort_if($mobileToken === null, Response::HTTP_UNAUTHORIZED);

        $owner = $mobileToken->user;
        $owner->resolveCurrentWorkspace();

        Auth::setUser($owner);

        return $next($request);
    }
}
