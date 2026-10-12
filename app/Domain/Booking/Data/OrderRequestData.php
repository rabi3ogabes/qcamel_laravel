<?php

namespace App\Domain\Booking\Data;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;

/**
 * Normalised, validated order request (F-ORD-003). Client totals, prices,
 * statuses and event ids are never part of it (T-036).
 */
final class OrderRequestData
{
    /**
     * @param  array{name:string,email:string,phone:string,country_code:string,nationality:?string,id_number:?string}  $customer
     * @param  list<array{ticket_id:string, quantity:int}>  $items
     * @param  list<array{ticket_id:string,name:string,phone:string,country_code:string,nationality:string,id_number:?string}>  $holders
     */
    public function __construct(
        public readonly array $customer,
        public readonly array $items,
        public readonly array $holders,
        public readonly PaymentMethod $paymentMethod,
        public readonly OrderSource $source,
        public readonly ?string $posUserId = null,
        public readonly bool $termsAccepted = false,
    ) {}

    public function totalQuantity(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    /** @return list<string> */
    public function ticketIds(): array
    {
        return array_column($this->items, 'ticket_id');
    }
}
