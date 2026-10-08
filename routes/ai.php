<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\SchedulerServer;
use Laravel\Mcp\Facades\Mcp;

// The Owner's Assistant connects here with `Authorization: Bearer <MCP_TOKEN>` (ADR 0003).
Mcp::web('/mcp', SchedulerServer::class)
    ->middleware(AuthenticateMcpToken::class)
    ->name('mcp');
