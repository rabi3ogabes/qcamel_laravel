<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Services\StockLedger;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Events\OrderStatusChanged;
use App\Jobs\RenderTicketQr;
use App\Models\Event;
use App\Models\ExpiredQrCode;
use App\Models\Order;
use App\Models\OrderEventChange;
use App\Models\TicketHolder;
use App\Support\Audit;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * F-ADM-ORD-032 rebuild (R-068, D-034, T-056): move a booking to another
 * event in ONE transaction — validate target, expire old codes, move stock
 * (lock both events) or refuse, issue new codes "<ref>-R<k>-TKT<NN>",
 * record history, audit; pictures re-rendered after commit.
 */
class ChangeOrderEventAction
{
    public function __construct(private readonly StockLedger $stock) {}

    /** @return array{expired:int, new_codes:int, change:OrderEventChange} */
    public function execute(Order $order, string $newEventId, Actor $actor): array
    {
        if (! $actor->can('orders.change_event')) {
            throw DomainError::forbidden();
        }
        if ($newEventId === $order->event_id) {
            throw DomainError::validation([__('orders.move_same_event')]);
        }
        $target = Event::query()->find($newEventId);
        if (! $target || $target->is_archived) {
            throw DomainError::validation([__('orders.move_invalid_target')]);
        }
        $oldEvent = Event::query()->withTrashed()->find($order->event_id);

        $result = DB::transaction(function () use ($order, $target, $oldEvent, $actor) {
            // R-047: lock both events' ticket rows in (event_id, id) order BEFORE the order row.
            $this->stock->lockEvents([$order->event_id, $target->id]);
            $o = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $holders = TicketHolder::query()->where('order_id', $o->id)->orderBy('position')->orderBy('created_at')->lockForUpdate()->get();

            // Stock follows the move only for active orders (R-046).
            if ($o->payment_status->isActive()) {
                try {
                    $this->stock->move($o, $o->event_id, $target->id);
                } catch (DomainError) {
                    throw new DomainError('insufficient_stock', 409, __('errors.insufficient_stock_move'));
                }
            }

            $now = QatarClock::nowUtc();
            $expired = 0;
            foreach (array_filter([$o->qr_code]) as $code) {
                ExpiredQrCode::query()->firstOrCreate(['qr_code' => $code], ['order_id' => $o->id, 'reason' => 'event_date_changed', 'moved_to_event_title' => $target->title, 'expired_at' => $now]);
                $expired++;
            }

            $k = OrderEventChange::query()->where('order_id', $o->id)->count() + 1;
            $targetTypes = $target->ticketTypes()->get()->keyBy(fn ($t) => $t->type->value);
            foreach ($holders as $i => $h) {
                if ($h->qr_code) {
                    ExpiredQrCode::query()->firstOrCreate(['qr_code' => $h->qr_code], [
                        'order_id' => $o->id, 'ticket_holder_id' => $h->id, 'reason' => 'event_date_changed',
                        'moved_to_event_title' => $target->title, 'expired_at' => $now,
                    ]);
                    $expired++;
                }
                $h->forceFill([
                    'qr_code' => $o->booking_reference.'-R'.$k.'-TKT'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'qr_image_url' => null,
                    'qr_failed' => false,
                    'ticket_type_id' => $targetTypes[$h->ticket_type->value]->id ?? $h->ticket_type_id,
                ])->save();
            }

            $o->forceFill(['event_id' => $target->id])->save();

            $change = OrderEventChange::create([
                'order_id' => $o->id,
                'old_event_id' => $oldEvent?->id, 'old_event_title' => $oldEvent?->title, 'old_event_date' => $oldEvent?->event_date,
                'new_event_id' => $target->id, 'new_event_title' => $target->title, 'new_event_date' => $target->event_date,
                'expired_codes_count' => $expired, 'new_codes_count' => $holders->count(), 'changed_by' => $actor->userId(),
            ]);

            Audit::log(ActivityType::EventChange, __('audit.event_change', ['ref' => $o->booking_reference, 'from' => $oldEvent?->title, 'to' => $target->title]), $o, [
                'order_id' => $o->id, 'booking_reference' => $o->booking_reference,
                'old_event_id' => $oldEvent?->id, 'old_event_title' => $oldEvent?->title ?? 'Unknown', 'old_event_date' => $oldEvent?->event_date?->toIso8601String() ?? '',
                'new_event_id' => $target->id, 'new_event_title' => $target->title, 'new_event_date' => $target->event_date?->toIso8601String(),
                'expired_qr_codes' => $expired,
            ], $actor);
            event(new OrderStatusChanged($o, $o->payment_status->value, $o->payment_status->value));

            return ['expired' => $expired, 'new_codes' => $holders->count(), 'change' => $change, 'holders' => $holders->pluck('id')->all()];
        }, 3);

        foreach ($result['holders'] as $holderId) {
            RenderTicketQr::dispatch($holderId)->afterCommit();
        }
        unset($result['holders']);

        return $result;
    }
}
