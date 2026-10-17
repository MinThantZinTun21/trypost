<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an endpoint with one bearer secret from the environment. An empty
 * secret switches the endpoint off (404); a wrong one is 401. A matching
 * request acts as the Owner, the install's first user, in their workspace.
 */
abstract class AuthenticatesWithOwnerToken
{
    /**
     * The configured secret; empty means the endpoint is off.
     */
    abstract protected function token(): string;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->token();

        abort_if($token === '', Response::HTTP_NOT_FOUND);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), Response::HTTP_UNAUTHORIZED);

        // Ids are ordered UUIDs, so they break a tie between users created in the same second.
        $owner = User::query()->oldest()->oldest('id')->first();

        abort_if($owner === null, Response::HTTP_NOT_FOUND);

        // Post policies check the current workspace, so make sure it is set.
        $owner->resolveCurrentWorkspace();

        Auth::setUser($owner);

        return $next($request);
    }
}
