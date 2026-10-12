<?php

namespace App\Domain\Messaging;

use App\Models\Order;
use App\Models\TicketHolder;
use App\Settings\ContactSettings;
use App\Support\PhoneNumber;
use App\Support\QatarClock;

/**
 * Server-built n8n payloads with EXACTLY the keys the existing workflows read
 * (F-NTF-025…035, T-097…T-107). The browser never builds or relays them (D-014).
 */
class N8nPayloads
{
    /** formatPhone (F-NTF-023): digits with country code, null when empty. */
    public static function formatPhone(?string $phone, ?string $cc = '+974'): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        return PhoneNumber::international($raw, trim((string) $cc) !== '' ? $cc : '+974');
    }

    private static function ts(): string
    {
        return QatarClock::nowUtc()->toIso8601ZuluString('millisecond');
    }

    private static function adminPhone(): ?string
    {
        try {
            return self::formatPhone(app(ContactSettings::class)->admin_phone, '+974');
        } catch (\Throwable) {
            return null;
        }
    }

    /** QR picture URL convention: stored URL, else <public>/qr-codes/<code>.png. */
    public static function qrUrl(TicketHolder $h): string
    {
        if ($h->qr_image_url) {
            return $h->qr_image_url;
        }
        $code = (string) $h->qr_code;

        return str_starts_with($code, 'http') ? $code : rtrim((string) config('app.url'), '/').'/storage/qr-codes/'.$code.'.png';
    }

    /** F-NTF-025 / T-043 / T-106: web cash-at-venue "order created". */
    public static function orderCreated(Order $order): array
    {
        $order->loadMissing(['customer', 'holders']);
        $c = $order->customer;

        return [
            'customer' => [
                'name' => $c?->name, 'email' => (string) ($c?->email ?? ''), 'phone' => self::formatPhone($c?->phone, $c?->country_code),
                'country_code' => $c?->country_code, 'nationality' => $c?->nationality, 'id_number' => $c?->getAttribute('id_number'),
            ],
            'order' => [
                'id' => $order->id, 'booking_reference' => $order->booking_reference, 'total_amount' => (float) $order->total_amount,
                'payment_method' => $order->payment_method->value, 'payment_status' => $order->payment_status->value, 'event_id' => $order->event_id,
            ],
            'ticketHolders' => $order->holders->map(fn (TicketHolder $h) => [
                'order_id' => $h->order_id, 'name' => $h->name,
                'phone' => self::formatPhone(PhoneNumber::stripPrefix($h->phone) ?: $h->phone, $h->country_code),
                'country_code' => $h->country_code, 'nationality' => $h->nationality, 'ticket_type' => $h->ticket_type->value,
                'qr_code' => $h->qr_code, 'qr_image_url' => $h->qr_image_url, 'qr_code_image' => $h->qr_image_url,
                'id_number' => $h->getAttribute('id_number'),
            ])->values()->all(),
            'bookingReference' => $order->booking_reference,
            'adminPhone' => self::adminPhone(),
            'timestamp' => self::ts(),
        ];
    }

    /** F-NTF-026 / T-107: "payment_confirmed" built entirely from the DB. */
    public static function paymentConfirmed(Order $order): array
    {
        $order->loadMissing(['customer', 'event', 'holders', 'items']);
        $prices = $order->items->mapWithKeys(fn ($i) => [$i->type->value => (float) $i->unit_price])->all();
        if ($prices === [] && $order->event) {
            $prices = $order->event->ticketTypes()->get()->mapWithKeys(fn ($t) => [$t->type->value => (float) $t->price])->all();
        }
        $orderRow = collect($order->attributesToArray())
            ->except(['return_origin', 'last_payment_check_at', 'idempotency_key', 'deleted_at', 'deleted_by'])
            ->all();
        $customer = $order->customer ? collect($order->customer->makeVisible('id_number')->attributesToArray())->except(['deleted_at'])->all() : null;
        $event = $order->event ? collect($order->event->attributesToArray())->except(['deleted_at'])->all() : null;

        return $orderRow + [
            'customers' => $customer,
            'events' => $event,
            'ticket_holders' => $order->holders->sortBy('qr_code')->values()->map(function (TicketHolder $h) use ($prices) {
                $image = $h->qr_image_url;

                return collect($h->makeVisible('id_number')->attributesToArray())->all() + [
                    'ticket_price' => $prices[$h->ticket_type->value] ?? 0,
                    'qr_code' => $image ?? $h->qr_code,
                    'qr_code_text' => $h->qr_code,
                    'qr_code_image' => $image,
                    'phone_international' => self::formatPhone(PhoneNumber::stripPrefix($h->phone) ?: $h->phone, $h->country_code),
                ];
            })->all(),
            'customer' => $customer,
            'event' => $event,
            'sadad_response' => $order->payment_id ? ['transaction_number' => $order->payment_id] : null,
            'action' => 'payment_confirmed',
            'timestamp' => self::ts(),
        ];
    }

    /** F-NTF-031 / F-ADM-INV-010 shape 2: WhatsApp invoice. */
    public static function whatsappInvoice(Order $order, ?int $totalQuantity = null, bool $withIds = true): array
    {
        $order->loadMissing(['customer', 'event', 'holders']);
        $c = $order->customer;
        $customer = $c ? ['name' => $c->name, 'email' => (string) ($c->email ?? ''), 'phone' => $c->phone, 'country_code' => $c->country_code, 'nationality' => $c->nationality]
            : ['name' => 'غير معروف', 'email' => '', 'phone' => '', 'country_code' => '+974', 'nationality' => null];
        $payload = [
            'order_id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'],
            'customer_phone_whatsapp' => PhoneNumber::toWhatsApp($customer['phone'], $customer['country_code'] ?: '+974'),
            'customer_email' => $customer['email'],
            'nationality' => $customer['nationality'],
            'ticket_type' => $order->ticket_type->value,
            'quantity' => $order->quantity,
            'total_quantity' => $totalQuantity ?? $order->quantity,
            'total_amount' => (float) $order->total_amount,
            'payment_status' => $order->payment_status->value,
            'qr_codes' => $order->holders->map(fn ($h) => self::qrUrl($h))->values()->all(),
            'ticket_types' => $order->holders->map(fn ($h) => $h->ticket_type->value)->values()->all(),
            'event_title' => $order->event?->title,
            'event_date' => $order->event?->event_date?->toIso8601String(),
            'event_location' => $order->event?->location,
            'created_at' => $order->created_at?->toIso8601String(),
            'customers' => $customer,
        ];
        if (! $withIds) {
            unset($payload['order_id'], $payload['customers']); // F-NTF-043 batch shape
        }

        return $payload;
    }

    /** F-NTF-032 shape 3: e-mail invoice (minus total_quantity, action first). */
    public static function emailInvoice(Order $order): array
    {
        $p = self::whatsappInvoice($order);
        unset($p['total_quantity']);

        return ['action' => 'send_email'] + $p;
    }

    /** F-NTF-033 shape 4: all tickets of a booking (customers screen). */
    public static function bookingTickets(Order $order): array
    {
        $order->loadMissing(['customer', 'event', 'holders']);
        $c = $order->customer;
        $first = $order->holders->first();
        $cc = ltrim((string) ($first?->country_code ?: $c?->country_code ?: '+974'), '+') ?: '974';

        return [
            'booking_reference' => $order->booking_reference,
            'event_title' => $order->event?->title,
            'event_location' => $order->event?->location,
            'event_date' => $order->event?->event_date?->toIso8601String(),
            'customer_name' => $c?->name,
            'customer_phone' => PhoneNumber::stripPrefix($c?->phone),
            'customer_phone_whatsapp' => PhoneNumber::toWhatsApp(PhoneNumber::stripPrefix($c?->phone), '+'.$cc),
            'nationality' => $c?->nationality ?: ($first?->nationality ?: ''),
            'quantity' => $order->quantity ?: $order->holders->count(),
            'qr_codes' => $order->holders->map(fn ($h) => self::qrUrl($h))->values()->all(),
            'ticket_types' => $order->holders->map(fn ($h) => $h->ticket_type->value)->values()->all(),
            'total_amount' => (float) $order->total_amount,
            'payment_status' => $order->payment_status->value,
            'timestamp' => self::ts(),
        ];
    }

    /** F-NTF-034 / F-QR-022 shape 5: one ticket (price from the order line, not the event list). */
    public static function singleTicket(TicketHolder $h): array
    {
        $h->loadMissing(['order.event', 'order.items']);
        $order = $h->order;
        $price = $h->unit_price ?? $order?->items->firstWhere('type', $h->ticket_type)?->unit_price ?? 0;
        $prefix = PhoneNumber::parsePrefix($h->phone);
        $cc = $prefix ? ltrim($prefix, '+') : (ltrim((string) $h->country_code, '+') ?: '974');

        return [
            'booking_reference' => $order?->booking_reference,
            'event_title' => $order?->event?->title,
            'event_location' => $order?->event?->location,
            'event_date' => $order?->event?->event_date?->toIso8601String(),
            'ticket_count' => 1,
            'holder' => [
                'name' => $h->name, 'phone' => PhoneNumber::stripPrefix($h->phone), 'country_code' => $cc,
                'nationality' => $h->nationality, 'id_number' => $h->getAttribute('id_number'), 'ticket_type' => $h->ticket_type->value,
                'ticket_price' => (float) $price, 'qr_code' => $h->qr_code, 'qr_code_image' => self::qrUrl($h), 'is_present' => $h->is_present,
            ],
            'timestamp' => self::ts(),
        ];
    }

    /** F-NTF-035 shape 6: send_ticket (now relayed by the server). */
    public static function sendTicket(Order $order): array
    {
        $order->loadMissing(['customer', 'holders', 'items']);
        $c = $order->customer;
        $prices = $order->items->mapWithKeys(fn ($i) => [$i->type->value => (float) $i->unit_price])->all();

        return [
            'customer' => ['name' => $c?->name, 'email' => (string) ($c?->email ?? ''), 'phone' => PhoneNumber::toWhatsApp($c?->phone, $c?->country_code ?: '+974')],
            'order' => [
                'id' => $order->id, 'booking_reference' => $order->booking_reference, 'ticket_type' => $order->ticket_type->value,
                'quantity' => $order->quantity, 'total_amount' => (float) $order->total_amount, 'payment_status' => $order->payment_status->value,
            ],
            'ticketHolders' => $order->holders->map(fn (TicketHolder $h) => [
                'name' => $h->name, 'phone' => PhoneNumber::toWhatsApp(PhoneNumber::stripPrefix($h->phone) ?: $h->phone, $h->country_code ?: '+974'),
                'nationality' => $h->nationality, 'id_number' => $h->getAttribute('id_number'), 'ticket_type' => $h->ticket_type->value,
                'ticket_price' => $prices[$h->ticket_type->value] ?? (float) $h->unit_price, 'qr_code' => $h->qr_code, 'qr_code_image' => self::qrUrl($h),
            ])->values()->all(),
            'bookingReference' => $order->booking_reference,
            'adminPhone' => self::adminPhone(),
            'timestamp' => self::ts(),
            'action' => 'send_ticket',
        ];
    }
}
