<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * Guards the Mobile app's API with one bearer secret, `MOBILE_TOKEN`
 * (ADR 0010), separate from `MCP_TOKEN` so either can be rotated alone.
 */
class AuthenticateMobileToken extends AuthenticatesWithOwnerToken
{
    protected function token(): string
    {
        return (string) config('trypost.mobile.token');
    }
}
