<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Queries\CustomerLookupQuery;
use App\Domain\Shared\Actor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F-ADM-CUS-017 / F-POS-017 rebuild: staff-only lookup, one query with history, PII masked for non-admins. */
class StaffLookupController extends Controller
{
    public function customers(Request $request, CustomerLookupQuery $q): JsonResponse
    {
        $term = trim((string) $request->query('term', ''));

        return response()->json(['customers' => mb_strlen($term) < 3 ? [] : $q->search($term, Actor::current(), $request->query('event_id'))]);
    }
}
