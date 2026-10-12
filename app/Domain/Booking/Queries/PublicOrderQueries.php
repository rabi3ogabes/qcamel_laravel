<?php

namespace App\Domain\Booking\Queries;

use App\Domain\Shared\Actor;
use App\Models\Order;
use App\Support\PhoneNumber;
use App\Support\Text;

/**
 * F-ORD-037 / F-PAY-049 status lookup (R-063, T-060, T-073) and F-ORD-038
 * invoice lookup (T-015, T-018, R-203, D-022). No PII beyond what the
 * reference holder needs; never payment_note, customer_id or id_number
 * (id_number only for admins).
 */
class PublicOrderQueries
{
    /**
     * @param  list<mixed>  $refs
     * @return list<array<string,mixed>>
     */
    public function status(array $refs): array
    {
        $valid = [];
        foreach (array_slice(array_values($refs), 0, 10) as $r) {
            if (($ref = Text::reference($r)) !== null) {
                $valid[$ref] = true;
            }
        }
        if ($valid === []) {
            return [];
        }

        return Order::query()
            ->whereIn('booking_reference', array_keys($valid))
            ->with(['event:id,title,event_date,end_date,location', 'holders' => fn ($q) => $q->select(['id', 'order_id', 'ticket_type', 'name', 'qr_code', 'qr_image_url', 'position'])->reorder('qr_code')])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $o) => [
                'booking_reference' => $o->booking_reference,
                'payment_status' => $o->publicStatus(),
                'payment_method' => $o->payment_method->value,
                'total_amount' => (string) $o->total_amount,
                'quantity' => $o->quantity,
                'ticket_type' => $o->ticket_type->value,
                'created_at' => $o->created_at?->toIso8601String(),
                'payment_expires_at' => $o->payment_expires_at?->toIso8601String(),
                'event_id' => $o->event_id,
                'event' => $o->event ? ['title' => $o->event->title, 'event_date' => $o->event->event_date?->toIso8601String(), 'location' => $o->event->location] : null,
                'tickets' => $o->holders->map(fn ($h) => ['ticket_type' => $h->ticket_type->value, 'name' => $h->name, 'qr_code' => $h->qr_code, 'qr_image_url' => $h->qr_image_url])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** Invoice data by reference only (UUID path dropped, D-022). */
    public function invoice(string $reference, ?Actor $actor = null): ?Order
    {
        if (Text::reference($reference) === null) {
            return null;
        }
        $order = Order::query()
            ->where('booking_reference', $reference)
            ->with(['customer', 'event', 'holders', 'items', 'posUser'])
            ->first();
        if ($order && ($actor?->canSeePii() ?? false)) {
            $order->holders->each->makeVisible('id_number');
            $order->customer?->makeVisible('id_number');
        }

        return $order;
    }

    /**
     * API shape of the invoice (T-015: holders without id_number for guests).
     *
     * @return array<string,mixed>
     */
    public function invoicePayload(Order $order, bool $pii = false): array
    {
        $c = $order->customer;

        return [
            'id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'ticket_type' => $order->ticket_type->value,
            'quantity' => $order->quantity,
            'total_amount' => (string) $order->total_amount,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'payment_id' => $order->payment_id,
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'customers' => $c ? [
                'name' => $c->name,
                'phone' => $pii ? $c->phone : PhoneNumber::mask($c->phone),
                'country_code' => $c->country_code,
                'nationality' => $c->nationality,
                'email' => $pii ? $c->email : null,
            ] : null,
            'events' => $order->event ? ['title' => $order->event->title, 'event_date' => $order->event->event_date?->toIso8601String(), 'location' => $order->event->location] : null,
            'ticket_holders' => $order->holders->map(fn ($h) => array_filter([
                'id' => $h->id, 'name' => $h->name,
                'phone' => $pii ? $h->phone : PhoneNumber::mask(PhoneNumber::stripPrefix($h->phone)),
                'country_code' => $h->country_code, 'nationality' => $h->nationality, 'ticket_type' => $h->ticket_type->value,
                'qr_code' => $h->qr_code, 'qr_image_url' => $h->qr_image_url, 'is_present' => $h->is_present,
                'checked_in_at' => $h->checked_in_at?->toIso8601String(), 'checked_in_by_name' => $h->checked_in_by_name,
                'id_number' => $pii ? $h->getAttribute('id_number') : '__omit__',
            ], fn ($v) => $v !== '__omit__'))->values()->all(),
        ];
    }
}
