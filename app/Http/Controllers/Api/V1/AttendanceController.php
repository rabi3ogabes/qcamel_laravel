<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Actor;
use App\Domain\Staff\AttendanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F-ADM-STF-014 / F-STF-019 staff-attendance contract (modes list/set/bulk_present/clear_day). */
class AttendanceController extends Controller
{
    public function __invoke(Request $request, AttendanceService $svc): JsonResponse
    {
        $actor = Actor::current();

        return response()->json(match ((string) $request->input('mode')) {
            'list' => (function () use ($svc, $request, $actor) {
                $r = $svc->list((string) $request->input('from'), (string) $request->input('to'), $actor);

                return ['users' => $r['users'], 'records' => $r['records']->map(fn ($x) => ['pos_user_id' => $x->pos_user_id, 'attendance_date' => $x->attendance_date->format('Y-m-d'), 'status' => $x->status->value]), 'can_edit' => $r['can_edit']];
            })(),
            'set' => tap(['success' => true], fn () => $svc->set((string) $request->input('date'), (string) $request->input('pos_user_id'), $request->input('status'), $actor)),
            'bulk_present' => ['success' => true, 'ids' => $svc->bulkPresent((string) $request->input('date'), $actor)],
            'clear_day' => ['success' => true, 'deleted' => $svc->clearDay((string) $request->input('date'), $actor)],
            default => ['success' => false, 'error' => 'bad_mode'],
        });
    }
}
