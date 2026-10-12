<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-083 / F-API-008 / T-037: JSON body ≤ 64 000 chars (413), invalid JSON 400,
 * OPTIONS 204, non-object handled by the validators.
 */
class LimitJsonBody
{
    public function handle(Request $request, Closure $next, int $max = 64000): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204);
        }
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $content = (string) $request->getContent();
            if (mb_strlen($content) > $max) {
                return response()->json(['success' => false, 'code' => 'payload_too_large', 'error' => 'Request body is too large'], 413);
            }
            if ($content !== '' && str_contains((string) $request->header('Content-Type'), 'json')) {
                json_decode($content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json(['success' => false, 'code' => 'invalid_json', 'error' => 'Request body must be valid JSON'], 400);
                }
            }
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
