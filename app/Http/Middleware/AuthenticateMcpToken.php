<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * Guards the MCP server with one bearer secret, `MCP_TOKEN` (ADR 0003).
 */
class AuthenticateMcpToken extends AuthenticatesWithOwnerToken
{
    protected function token(): string
    {
        return (string) config('trypost.mcp.token');
    }
}
