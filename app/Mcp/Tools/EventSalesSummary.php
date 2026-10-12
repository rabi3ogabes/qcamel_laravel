<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadOnlyTool;
use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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

/** F-API-003 rebuild — revenue from stored line totals (D-070), remaining = capacity − held. */
#[Name('event_sales_summary')]
#[Title('Event sales summary')]
#[Description('Ticket sales and gate attendance for one event: tickets sold per type, revenue, capacity left and how many holders have been scanned in.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class EventSalesSummary extends Tool
{
    use ReadOnlyTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->deny('tickets.view')) {
            return $denied;
        }
        $data = $request->validate(['event_id' => 'required|uuid']);
        $event = Event::query()->with('ticketTypes')->find($data['event_id']);
        if (! $event) {
            return Response::error('Event not found');
        }

        $confirmed = fn () => DB::table('orders')->whereNull('deleted_at')->where('event_id', $event->id)->where('payment_status', 'confirmed');
        $holders = DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')
            ->whereNull('o.deleted_at')->where('o.event_id', $event->id)->where('o.payment_status', 'confirmed')
            ->selectRaw("h.ticket_type as t, COUNT(*) as sold, SUM(CASE WHEN h.is_present THEN 1 ELSE 0 END) as scanned, SUM(CASE WHEN o.payment_method = 'sadad' THEN 1 ELSE 0 END) as online")
            ->groupBy('h.ticket_type')->get()->keyBy('t');
        $revenue = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereNull('o.deleted_at')->where('o.event_id', $event->id)->where('o.payment_status', 'confirmed')
            ->selectRaw('i.type as t, SUM(i.line_total) as a')->groupBy('i.type')->pluck('a', 't');

        $types = $event->ticketTypes->sortBy(fn ($t) => $t->type->sortWeight())->map(function ($t) use ($holders, $revenue) {
            $k = $t->type->value;

            return [
                'type' => $k,
                'sold' => (int) ($holders[$k]->sold ?? 0),
                'scanned' => (int) ($holders[$k]->scanned ?? 0),
                'price' => (float) $t->price,
                'revenue' => round((float) ($revenue[$k] ?? 0), 2),
                'capacity' => (int) $t->available_quantity,
                'held_including_pending' => (int) $t->sold_quantity,
                'remaining' => max(0, (int) $t->available_quantity - (int) $t->sold_quantity),
            ];
        })->values()->all();

        $sold = array_sum(array_column($types, 'sold'));
        $online = (int) $holders->sum('online');
        $summary = [
            'event' => ['id' => $event->id, 'title' => $event->title, 'event_date' => self::qatar($event->event_date), 'end_date' => self::qatar($event->end_date)],
            'orders_confirmed' => $confirmed()->count(),
            'tickets_sold' => $sold,
            'tickets_scanned' => array_sum(array_column($types, 'scanned')),
            'tickets_sold_online' => $online,
            'tickets_sold_at_counter' => $sold - $online,
            'revenue_qar' => round((float) $confirmed()->sum('total_amount'), 2),
            'ticket_types' => $types,
        ];

        return Response::make(Response::text("{$event->title}: {$sold} tickets sold, {$summary['tickets_scanned']} scanned in, {$summary['revenue_qar']} QAR."))
            ->withStructuredContent($summary);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->string()->format('uuid')->description('Event id, as returned by list_events. Pass the current event when unsure.')->required(),
        ];
    }
}
