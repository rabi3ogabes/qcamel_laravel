<?php

namespace App\Domain\Payments\Sadad;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * F-PAY-011…019 Web Checkout 2.1 request builder (R-100…R-105, T-042,
 * T-075…T-077, T-082).
 */
class SadadCheckoutBuilder
{
    public function __construct(private readonly SadadConfig $config) {}

    /**
     * Pure builder (mirrors buildCheckoutRequest of the original).
     *
     * @param  list<array{name:string, price:mixed, quantity:int}>  $items
     * @return array{url:string, fields:array<string,string>}
     */
    public static function build(string $merchantId, string $secret, string $orderId, mixed $amount, string $website, string $callbackUrl, string $mobile, string $email, ?CarbonImmutable $now = null, array $items = []): array
    {
        if ($secret === '') {
            throw new InvalidArgumentException('Sadad secret key is not configured');
        }
        if ($merchantId === '') {
            throw new InvalidArgumentException('Sadad merchant id is not configured');
        }
        if ($website === '') {
            throw new InvalidArgumentException('Sadad website is not configured');
        }
        if (! str_starts_with($callbackUrl, 'https://')) {
            throw new InvalidArgumentException('Callback URL must use https');
        }

        $signed = [
            'merchant_id' => $merchantId,
            'ORDER_ID' => $orderId,
            'TXN_AMOUNT' => self::formatAmount($amount),
            'WEBSITE' => $website,
            'CALLBACK_URL' => $callbackUrl,
            'MOBILE_NO' => $mobile,
            'EMAIL' => $email,
            'txnDate' => self::qatarDate($now ?? QatarClock::nowUtc()),
        ];
        $fields = $signed + ['signature' => SadadSigner::sign($signed, $secret)];
        foreach (array_values($items) as $i => $item) {
            $fields["productdetail[$i][order_id]"] = $orderId;
            $fields["productdetail[$i][itemname]"] = $item['name'];
            $fields["productdetail[$i][amount]"] = self::formatAmount($item['price']);
            $fields["productdetail[$i][quantity]"] = (string) $item['quantity'];
        }

        return ['url' => SadadConfig::CHECKOUT_URL, 'fields' => $fields];
    }

    /** R-103 / T-076: exactly 2 decimals from integer cents; rejects ≤0, NaN, INF. */
    public static function formatAmount(mixed $amount): string
    {
        if (is_float($amount) && ! is_finite($amount)) {
            throw new InvalidArgumentException('Invalid amount');
        }
        $m = Money::tryOf($amount);
        if (! $m || $m->cents <= 0) {
            throw new InvalidArgumentException('Invalid amount');
        }

        return $m->toDecimal();
    }

    /** R-008 / T-077: Qatar calendar date at request time. */
    public static function qatarDate(CarbonImmutable $now): string
    {
        return $now->setTimezone('Asia/Qatar')->toDateString();
    }

    /** R-105: fallback e-mail from the website domain. */
    public static function fallbackEmail(?string $websiteDomain): string
    {
        $host = strtolower(trim((string) $websiteDomain));
        $host = preg_replace('#^[a-z]+://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        if ($host === '') {
            return 'noreply@example.invalid';
        }

        return str_contains($host, '.') ? "noreply@$host" : "noreply@$host.invalid";
    }

    /**
     * Build the signed request for an order from its frozen order_items
     * (D-033) and log a payment_attempts row.
     *
     * @return array{url:string, fields:array<string,string>}
     */
    public function forOrder(Order $order, string $kind = 'create', ?string $clientIp = null): array
    {
        $order->loadMissing(['customer', 'items']);
        $items = $order->items->sortByDesc(fn ($i) => Money::of((string) $i->unit_price)->cents)->values()
            ->filter(fn ($i) => Money::of((string) $i->unit_price)->cents > 0)
            ->map(fn ($i) => ['name' => $i->type->sadadItemName(), 'price' => (string) $i->unit_price, 'quantity' => (int) $i->quantity])
            ->values()->all();

        $customer = $order->customer;
        $email = trim((string) $customer?->email);
        $request = self::build(
            merchantId: (string) $this->config->merchantId(),
            secret: (string) $this->config->checkoutSecret(),
            orderId: $order->booking_reference,
            amount: (string) $order->total_amount,
            website: (string) $this->config->websiteDomain(),
            callbackUrl: $this->config->callbackUrl(),
            mobile: PhoneNumber::international($customer?->phone, $customer?->country_code ?: '+974'),
            email: $email !== '' ? $email : self::fallbackEmail($this->config->websiteDomain()),
            items: $items,
        );

        PaymentAttempt::create([
            'order_id' => $order->id,
            'amount' => (string) $order->total_amount,
            'txn_date' => $request['fields']['txnDate'],
            'signature_hash' => hash('sha256', $request['fields']['signature']),
            'return_base' => $this->config->returnBase($order->return_origin),
            'client_ip' => $clientIp,
            'kind' => $kind,
        ]);

        return $request;
    }
}
