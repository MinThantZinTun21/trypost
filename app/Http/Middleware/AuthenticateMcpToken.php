<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP server with one bearer secret, `MCP_TOKEN` (ADR 0003). An
 * empty secret switches the endpoint off. A matching request acts as the
 * Owner, the install's first user.
 */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('trypost.mcp.token');

        abort_if($token === '', Response::HTTP_NOT_FOUND);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), Response::HTTP_UNAUTHORIZED);

        $owner = User::query()->oldest()->first();

        abort_if($owner === null, Response::HTTP_NOT_FOUND);

        Auth::setUser($owner);

        return $next($request);
    }
}
