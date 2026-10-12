<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Events\TicketCapacityIncreased;
use App\Events\TicketTypeStockChanged;
use App\Models\TicketType;
use App\Support\Audit;
use App\Support\Money;
use App\Support\PublicSettings;
use Illuminate\Support\Facades\DB;

/**
 * F-ADM-TKT-011/012 rebuild: price / capacity / description edit under a row
 * lock; capacity can never drop below held seats (pending + confirmed);
 * historical revenue is untouched (price snapshots on holders/items).
 */
class UpdateTicketTypeAction
{
    public function execute(TicketType $ticket, string $price, int $capacity, ?string $description, Actor $actor): TicketType
    {
        if (! $actor->can('tickets.edit')) {
            throw DomainError::forbidden();
        }
        $money = Money::tryOf($price);
        if ($money === null || $money->cents < 0) {
            throw DomainError::validation([__('admin.tkt.invalid_price')]);
        }
        if ($capacity < 0) {
            throw DomainError::validation([__('admin.tkt.invalid_capacity')]);
        }

        return DB::transaction(function () use ($ticket, $money, $capacity, $description, $actor) {
            $row = TicketType::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($capacity < (int) $row->sold_quantity) {
                throw new DomainError('capacity_below_sold', 422, null, ['replace' => ['sold' => $row->sold_quantity]]);
            }
            $old = ['price' => (string) $row->price, 'capacity' => (int) $row->available_quantity, 'description' => $row->description];
            $row->forceFill([
                'price' => $money->toDecimal(),
                'available_quantity' => $capacity,
                'description' => ($d = trim((string) $description)) === '' ? null : mb_substr($d, 0, 500),
            ])->save();

            if ($old['price'] !== $money->toDecimal()) {
                Audit::log(ActivityType::PriceChanged, __('admin.tkt.audit_price', ['type' => $row->type->label()]), $row, ['old' => $old['price'], 'new' => $money->toDecimal(), 'event_id' => $row->event_id], $actor);
            }
            if ($old['capacity'] !== $capacity) {
                Audit::log(ActivityType::CapacityChanged, __('admin.tkt.audit_capacity', ['type' => $row->type->label()]), $row, ['old' => $old['capacity'], 'new' => $capacity, 'event_id' => $row->event_id], $actor);
                if ($capacity > $old['capacity']) {
                    event(new TicketCapacityIncreased($row->event_id, $row->type->value, $old['capacity'], $capacity));
                }
                event(new TicketTypeStockChanged($row->event_id, $row->type->value, $row->remaining, (int) $row->sold_quantity, $capacity));
            }
            DB::afterCommit(fn () => PublicSettings::flush());

            return $row;
        });
    }
}
