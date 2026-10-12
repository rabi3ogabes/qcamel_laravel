<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Booking\Services\StockLedger;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Events\TicketHolderChanged;
use App\Models\DeletedTicket;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketHolder;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Recycle bin (R-194, F-ORD-042, F-ADM-TKT-024…026, D-047, T-055):
 * bin keeps every field incl. qr_image_url + context, releases one seat when
 * the order is active; restore re-inserts with the SAME id, re-checking stock
 * and the per-person cap; purge = permanent delete (super admin).
 */
class RecycleBinActions
{
    public function __construct(private readonly StockLedger $stock, private readonly PersonLimitGuard $limits) {}

    public function bin(TicketHolder $holder, Actor $actor): DeletedTicket
    {
        if (! $actor->can('tickets.bin')) {
            throw new DomainError('forbidden', 403, __('tickets.admin_only'));
        }

        return DB::transaction(function () use ($holder, $actor) {
            $h = TicketHolder::query()->whereKey($holder->id)->first();
            if (! $h) {
                throw new DomainError('not_found', 404, __('tickets.not_found'));
            }
            $order = Order::query()->withTrashed()->with(['event', 'customer'])->findOrFail($h->order_id);
            $locked = $this->stock->lockEvent($order->event_id);
            $h = TicketHolder::query()->whereKey($holder->id)->lockForUpdate()->firstOrFail();

            $bin = DeletedTicket::create([
                'holder_id' => $h->id, 'order_id' => $h->order_id, 'ticket_type_id' => $h->ticket_type_id,
                'name' => $h->name, 'phone' => $h->phone, 'nationality' => $h->nationality ?? '', 'ticket_type' => $h->ticket_type,
                'qr_code' => $h->qr_code, 'qr_image_url' => $h->qr_image_url, 'is_present' => $h->is_present,
                'checked_in_at' => $h->checked_in_at, 'checked_in_by' => $h->checked_in_by, 'checked_in_by_name' => $h->checked_in_by_name,
                'id_number' => $h->id_number, 'country_code' => $h->country_code, 'unit_price' => $h->unit_price, 'position' => $h->position,
                'holder_created_at' => $h->created_at,
                'context' => [
                    'booking_reference' => $order->booking_reference,
                    'payment_status' => $order->payment_status->value,
                    'payment_method' => $order->payment_method->value,
                    'event_title' => $order->event?->title,
                    'event_date' => $order->event?->event_date?->toIso8601String(),
                    'customer_name' => $order->customer?->name,
                ],
                'deleted_at' => now(),
                'deleted_by' => $actor->userId(),
                'deleted_by_name' => $actor->user?->email,
            ]);
            if ($order->payment_status->isActive()) {
                $this->stock->release($order->event_id, [$h->ticket_type->value => 1], $locked);
            }
            $h->delete();
            event(new TicketHolderChanged($h->id, $order->id, $order->event_id, 'deleted'));
            Audit::log(ActivityType::TicketBinned, __('tickets.binned'), $order, ['holder_id' => $h->id, 'qr_code' => $h->qr_code, 'holder_name' => $h->name], $actor);

            return $bin;
        }, 3);
    }

    public function restore(DeletedTicket $item, Actor $actor): TicketHolder
    {
        if (! $actor->can('tickets.restore')) {
            throw new DomainError('forbidden', 403, __('tickets.admin_only'));
        }

        return DB::transaction(function () use ($item, $actor) {
            $bin = DeletedTicket::query()->whereKey($item->id)->first();
            if (! $bin) {
                throw new DomainError('not_found', 404, __('tickets.bin_item_missing'));
            }
            $order = Order::query()->with('event')->find($bin->order_id);
            if (! $order) {
                throw new DomainError('order_not_found', 404, __('tickets.restore_order_gone'));
            }
            $locked = $this->stock->lockEvent($order->event_id);
            if ($order->payment_status->isActive()) {
                $this->stock->reserve($order->event_id, [$bin->ticket_type->value => 1], $locked, withType: true);
                $this->limits->assert($order->event_id, [[
                    'name' => $bin->name, 'id_number' => $bin->id_number, 'phone' => $bin->phone, 'type' => $bin->ticket_type->value,
                ]], (int) ($order->event?->limit('person') ?? 5));
            }
            $h = new TicketHolder;
            $h->forceFill([
                'id' => $bin->holder_id, 'order_id' => $bin->order_id, 'ticket_type_id' => $bin->ticket_type_id,
                'ticket_type' => $bin->ticket_type, 'unit_price' => $bin->unit_price, 'name' => $bin->name, 'phone' => $bin->phone,
                'country_code' => $bin->country_code, 'nationality' => $bin->nationality ?? '', 'id_number' => $bin->id_number,
                'position' => $bin->position ?? 1, 'qr_code' => $bin->qr_code, 'qr_image_url' => $bin->qr_image_url,
                'is_present' => (bool) $bin->is_present, 'checked_in_at' => $bin->checked_in_at, 'checked_in_by' => $bin->checked_in_by,
                'checked_in_by_name' => $bin->checked_in_by_name,
            ]);
            $h->created_at = $bin->holder_created_at ?? now();
            $h->save();
            $bin->delete();
            event(new TicketHolderChanged($h->id, $order->id, $order->event_id, 'restored'));
            Audit::log(ActivityType::TicketRestored, __('tickets.restored'), $order, ['holder_id' => $h->id, 'qr_code' => $h->qr_code], $actor);

            return $h;
        }, 3);
    }

    public function purge(DeletedTicket $item, Actor $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw DomainError::forbidden();
        }
        $item->delete();
        Audit::log(ActivityType::TicketBinned, __('tickets.purged'), null, ['holder_id' => $item->holder_id, 'qr_code' => $item->qr_code], $actor);
    }

    /** R-050 recount (admin) → number of rows corrected. */
    public function recount(Actor $actor): int
    {
        if (! $actor->can('stock.recount')) {
            throw DomainError::forbidden();
        }
        $changed = DB::transaction(fn () => $this->stock->recount(true), 3);
        Audit::log(ActivityType::StockRecount, __('tickets.recounted', ['n' => count($changed)]), null, ['changed' => $changed], $actor);

        return count($changed);
    }

    /** Admin "un-mark attendance" (F-ADM-CUS-016 rebuild) lives in CheckInService::reset. */
    public static function eventOf(DeletedTicket $bin): ?Event
    {
        return Order::query()->withTrashed()->find($bin->order_id)?->event;
    }
}
