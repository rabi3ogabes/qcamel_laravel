<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Booking\Queries\AvailabilityQuery;
use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Shared\DomainError;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\PublicSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public catalogue endpoints (T-012, T-014, T-061, F-ORD-032…035). */
class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $current = PublicSettings::get('current_event_id');
        $events = Event::query()->public()
            ->when($current, fn ($q) => $q->whereKey($current))
            ->with('ticketTypes')
            ->orderBy('event_date')->orderBy('display_order')->get()
            ->filter(fn (Event $e) => $e->canPurchaseTickets())
            ->map(fn (Event $e) => [
                'id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'event_date' => $e->event_date?->toIso8601String(),
                'end_date' => $e->end_date?->toIso8601String(), 'start_time' => $e->start_time, 'end_time' => $e->end_time,
                'location' => $e->location, 'image_url' => $e->coverUrl(),
                'tickets' => $e->ticketTypes->map(fn ($t) => ['id' => $t->id, 'type' => $t->type->value, 'price' => (string) $t->price])->values(),
            ])->values();

        return response()->json(['success' => true, 'events' => $events]);
    }

    public function availability(Event $event, AvailabilityQuery $q, ExpireHoldsAction $expire): JsonResponse
    {
        if (! $event->is_active || $event->is_archived) {
            throw DomainError::notFound('Event not found');
        }
        $expire->forEvent($event->id); // F-PUB-024

        return response()->json(['success' => true, 'tickets' => $q->forEvent($event)]);
    }

    /** R-054 check_availability (T-014). */
    public function check(Request $request, AvailabilityQuery $q): JsonResponse
    {
        $data = $request->validate(['event_id' => 'required|uuid', 'ticket_type' => 'required|string', 'quantity' => 'required|integer|min:1|max:20']);

        return response()->json($q->check($data['event_id'], $data['ticket_type'], (int) $data['quantity']));
    }

    /** release_expired_holds (T-014, T-061): guest-callable, returns closed count. */
    public function releaseExpired(Request $request, ExpireHoldsAction $expire): JsonResponse
    {
        $data = $request->validate(['event_id' => 'required|uuid']);

        return response()->json(['success' => true, 'released' => $expire->forEvent($data['event_id'])]);
    }

    /** F-ORD-035 person-count (rate-limited 20/min/IP; number only). */
    public function personCount(Request $request, PersonLimitGuard $guard): JsonResponse
    {
        $data = $request->validate(['event_id' => 'required|uuid', 'id_number' => 'nullable|string|max:30', 'phone' => 'nullable|string|max:32']);

        return response()->json(['success' => true, 'count' => $guard->count($data['id_number'] ?? null, $data['phone'] ?? null, $data['event_id'])]);
    }
}
