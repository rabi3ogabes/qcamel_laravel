<?php

namespace App\Http\Middleware;

use App\Domain\Analytics\VisitorTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** F-PUB-091 rebuild: server-side page views for public GET pages only. */
class TrackVisitor
{
    public function __construct(private readonly VisitorTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && ! $request->ajax() && ! $request->header('X-Livewire')
            && $response->getStatusCode() < 400
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $this->tracker->track($request);
        }

        return $response;
    }
}
