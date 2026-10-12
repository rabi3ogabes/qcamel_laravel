<?php

namespace App\Http\Controllers\Public;

use App\Domain\Analytics\VisitorTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** F-PUB-091 rebuild: 60 s presence heartbeat (navigator.sendBeacon('/v')). */
class PresenceController extends Controller
{
    public function __invoke(Request $request, VisitorTracker $tracker): Response
    {
        $path = $request->input('p');
        $tracker->heartbeat($request, is_string($path) ? mb_substr($path, 0, 255) : null);

        return response()->noContent();
    }
}
