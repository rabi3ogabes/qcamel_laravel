<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Actor;
use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\GateBoardQuery;
use App\Enums\ScanQueueResult;
use App\Events\ScanQueueProcessed;
use App\Http\Controllers\Controller;
use App\Models\ScanQueueItem;
use App\Support\Formats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gate API (ticket-checkin modes, F-GATE-037…047; T-118…T-121). Business
 * refusals are HTTP 200 with {success:false, code, message} (F-GATE-048).
 */
class ScannerController extends Controller
{
    public function __construct(private readonly CheckInService $checkin) {}

    private function actor(Request $request): Actor
    {
        return $request->attributes->get('actor') ?? Actor::current();
    }

    public function checkin(Request $request): JsonResponse
    {
        $code = (string) ($request->input('booking_reference') ?? $request->input('code') ?? '');

        return response()->json($this->checkin->admitByCode($code, $this->actor($request), $request->input('staff_name'), (string) $request->input('source', 'camera')));
    }

    public function manual(Request $request): JsonResponse
    {
        $ids = $request->input('holder_ids');
        if (is_array($ids)) {
            return response()->json(['success' => true, 'results' => $this->checkin->admitMany(array_map('strval', $ids), $this->actor($request), $request->input('staff_name'))]);
        }

        return response()->json($this->checkin->admitHolder((string) $request->input('holder_id', ''), $this->actor($request), $request->input('staff_name')));
    }

    public function lookup(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $code = (string) $request->input('code', '');
        $type = CheckInService::classify($code);
        $this->checkin->logSearch($code, $type, $actor);

        return response()->json(match ($type) {
            'phone' => $this->checkin->byPhone($code, $actor),
            'booking_reference' => ($r = $this->checkin->byReference($code, $actor))['success'] ? $r : $this->checkin->lookup($code, $actor),
            default => $this->checkin->lookup($code, $actor),
        });
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json($this->checkin->search((string) $request->input('value', ''), $this->actor($request)));
    }

    public function history(Request $request): JsonResponse
    {
        $rows = $this->checkin->history($request->input('event_id'), (int) config('qcamelmc.gate.history_limit', 30));

        return response()->json(['success' => true, 'message' => 'ok', 'history' => $rows->map(fn ($h) => [
            'id' => $h->id, 'name' => $h->name, 'ticket_type' => $h->ticket_type->value, 'qr_code' => $h->qr_code,
            'confirmed_at' => $h->checked_in_at?->toIso8601String(), 'confirmed_at_label' => Formats::dateTime($h->checked_in_at),
            'confirmed_by_name' => $h->checked_in_by_name, 'booking_reference' => $h->order?->booking_reference, 'event_title' => $h->order?->event?->title,
        ])->values()]);
    }

    public function reset(Request $request): JsonResponse
    {
        return response()->json($this->checkin->reset((string) $request->input('holder_id', ''), $this->actor($request), $request->input('reason')));
    }

    public function gateBoard(Request $request, GateBoardQuery $q): JsonResponse
    {
        return response()->json($q->forEvent($request->query('event_id')));
    }

    /**
     * Tier B1 offline sync: idempotent replay by client UUID, applied in
     * scanned_at order with the normal atomic admit (T-120).
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => 'required|array|max:500',
            'scans.*.id' => 'required|uuid',
            'scans.*.code' => 'required|string|max:200',
            'scans.*.scanned_at' => 'required|date',
            'scans.*.staff_name' => 'nullable|string|max:80',
        ]);
        $actor = $this->actor($request);
        $results = [];
        foreach (collect($data['scans'])->sortBy('scanned_at') as $scan) {
            $existing = ScanQueueItem::query()->find($scan['id']);
            if ($existing && $existing->processed_at) {
                $results[] = ['id' => $scan['id'], 'result' => $existing->result->value, 'reason' => $existing->reason];

                continue;
            }
            $item = $existing ?? ScanQueueItem::create([
                'id' => $scan['id'], 'kiosk_device_id' => $actor->deviceId(), 'user_id' => $actor->userId(),
                'ticket_code' => mb_substr($scan['code'], 0, 100), 'scanned_at' => $scan['scanned_at'], 'received_at' => now(),
                'result' => ScanQueueResult::Pending, 'actor_label' => $scan['staff_name'] ?? null,
            ]);
            $r = $this->checkin->admitByCode($scan['code'], $actor, $scan['staff_name'] ?? null, 'offline_sync');
            $result = $r['ok'] ? ScanQueueResult::Admitted : (($r['code'] ?? null) === 'already_checked_in' ? ScanQueueResult::AlreadyUsed : ScanQueueResult::Rejected);
            $item->forceFill(['processed_at' => now(), 'result' => $result, 'reason' => $r['ok'] ? null : ($r['code'] ?? null), 'ticket_holder_id' => $r['ticket']['holder_id'] ?? null])->save();
            $results[] = ['id' => $scan['id'], 'result' => $result->value, 'reason' => $item->reason, 'message' => $r['message'] ?? null];
        }
        if ($actor->deviceId()) {
            event(new ScanQueueProcessed($actor->deviceId(), count($results)));
        }

        return response()->json(['success' => true, 'results' => $results]);
    }
}
