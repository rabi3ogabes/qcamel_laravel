<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-API-001 rebuild: the MCP endpoint accepts only a Sanctum bearer token
 * with the `mcp:read` ability that belongs to an active account holding the
 * `api.mcp` permission. The token's user becomes the request user, so every
 * tool runs with that account's permissions (Actor::current()).
 */
class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('qcamelmc.mcp.enabled', true)) {
            return response()->json(['error' => 'mcp_disabled'], 404);
        }
        $user = $request->bearerToken() ? auth('sanctum')->user() : null;
        if (! $user instanceof User || ! $user->is_active) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Not authenticated'], 401, ['WWW-Authenticate' => 'Bearer']);
        }
        if (! $user->tokenCan('mcp:read') || ! $user->can('api.mcp')) {
            return response()->json(['error' => 'forbidden', 'message' => 'This token cannot use the MCP server'], 403);
        }
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
