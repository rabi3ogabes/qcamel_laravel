<?php

namespace App\Mcp\Tools;

use App\Domain\Shared\Actor;
use App\Mcp\Tools\Concerns\ReadOnlyTool;
use App\Models\Order;
use App\Support\Text;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** F-API-004 — personal fields only with customers.view_pii (national IDs are never returned). */
#[Name('find_booking')]
#[Title('Find a booking')]
#[Description('Look up one booking by its reference (for example POS-21-09-2026-UY956SR9R) and return the customer, payment status and every ticket holder with their check-in state.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class FindBooking extends Tool
{
    use ReadOnlyTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny('orders.view')) {
            return $denied;
        }
        $ref = mb_strtoupper(trim((string) $request->get('booking_reference')));
        if (mb_strlen($ref) < 4) {
            return Response::error('booking_reference must be at least 4 characters.');
        }
        $order = Order::query()->with(['customer', 'event', 'holders'])->where('booking_reference', $ref)->first();
        if (! $order) {
            return Response::error('No booking found with that reference');
        }
        $pii = Actor::current()->can('customers.view_pii');
        $mask = fn (?string $v) => $v === null || $v === '' ? null : ($pii ? $v : Text::maskPhone($v));

        $booking = [
            'booking_reference' => $order->booking_reference,
            'payment_status' => $order->payment_status?->value,
            'payment_method' => $order->payment_method?->value,
            'total_amount' => (float) $order->total_amount,
            'created_at' => self::qatar($order->created_at),
            'customer' => [
                'name' => $order->customer?->name,
                'phone' => $mask($order->customer?->phone),
                'email' => $pii ? $order->customer?->email : null,
            ],
            'event' => ['title' => $order->event?->title, 'event_date' => self::qatar($order->event?->event_date)],
            'holders' => $order->holders->sortBy('position')->map(fn ($h) => [
                'name' => $h->name,
                'phone' => $mask($h->phone),
                'nationality' => $h->nationality ?: null,
                'ticket_type' => is_object($h->ticket_type) ? $h->ticket_type->value : $h->ticket_type,
                'checked_in' => (bool) $h->is_present,
                'checked_in_at' => self::qatar($h->checked_in_at),
                'checked_in_by' => $h->checked_in_by_name,
            ])->values()->all(),
        ];
        $n = count($booking['holders']);

        return Response::make(Response::text("{$order->booking_reference} — ".($booking['customer']['name'] ?? '?')." — {$n} ticket(s), payment {$booking['payment_status']}."))
            ->withStructuredContent(['booking' => $booking]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'booking_reference' => $schema->string()->min(4)->description('The booking reference printed on the invoice, e.g. QTR-12-10-2026-3155785A2DEE.')->required(),
        ];
    }
}
