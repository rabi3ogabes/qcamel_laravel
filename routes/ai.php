<?php

use App\Http\Middleware\EnsureMcpAccess;
use App\Mcp\Servers\QcamelServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP server (F-API-001…005) — read-only AI tools, Sanctum token with the
| `mcp:read` ability + `api.mcp` permission, 60 calls/minute per account.
|--------------------------------------------------------------------------
*/
Mcp::web('/mcp', QcamelServer::class)
    ->middleware([EnsureMcpAccess::class, 'throttle:mcp']);
