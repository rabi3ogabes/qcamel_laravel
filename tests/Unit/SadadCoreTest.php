<?php

use App\Domain\Payments\Sadad\NormalizedPayment;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Domain\Payments\Sadad\SadadCheckoutBuilder;
use App\Domain\Payments\Sadad\SadadSigner;
use Carbon\CarbonImmutable;

/*
| Sadad Web Checkout 2.1 pure functions — T-075…T-079, T-081 (F-PAY-011…030).
*/

const SADAD_SECRET = 'S3CR3T';

it('T-075 signs the reference field set byte for byte', function () {
    $fields = [
        'merchant_id' => '1664851', 'ORDER_ID' => 'QTR-ABC123DEF456', 'TXN_AMOUNT' => '150.00', 'WEBSITE' => 'example.qa',
        'CALLBACK_URL' => 'https://x.supabase.co/functions/v1/sadad-callback', 'MOBILE_NO' => '97455512345', 'EMAIL' => 'a@b.qa', 'txnDate' => '2026-10-09',
    ];

    expect(SadadSigner::sign($fields, SADAD_SECRET))->toBe('6A6D258727B321A8EC7C02CAFD96B2F14D754BA1627029F2920D27E11F4D0B49');
});

it('T-075 excludes signature and productdetail from the signed set', function () {
    $fields = ['merchant_id' => '1664851', 'ORDER_ID' => 'QTR-ABC123DEF456', 'TXN_AMOUNT' => '150.00', 'WEBSITE' => 'example.qa',
        'CALLBACK_URL' => 'https://x.supabase.co/functions/v1/sadad-callback', 'MOBILE_NO' => '97455512345', 'EMAIL' => 'a@b.qa', 'txnDate' => '2026-10-09'];
    $noisy = $fields + ['signature' => 'X', 'productdetail[0][amount]' => '150.00'];

    expect(SadadSigner::sign($noisy, SADAD_SECRET))->toBe(SadadSigner::sign($fields, SADAD_SECRET));
});

it('T-076 formats amounts exactly and rejects invalid ones', function () {
    expect(SadadCheckoutBuilder::formatAmount(0.1 + 0.2))->toBe('0.30')
        ->and(SadadCheckoutBuilder::formatAmount('150'))->toBe('150.00');

    foreach ([0, -5, NAN, INF] as $bad) {
        expect(fn () => SadadCheckoutBuilder::formatAmount($bad))->toThrow(InvalidArgumentException::class);
    }
});

it('T-076 refuses empty secret, empty merchant and non-https callbacks', function (array $args) {
    expect(fn () => SadadCheckoutBuilder::build(...$args))->toThrow(InvalidArgumentException::class);
})->with([
    'no secret' => [['1664851', '', 'QTR-A', '10', 'example.qa', 'https://x/cb', '974', 'a@b.qa']],
    'no merchant' => [['', 'S', 'QTR-A', '10', 'example.qa', 'https://x/cb', '974', 'a@b.qa']],
    'http callback' => [['1664851', 'S', 'QTR-A', '10', 'example.qa', 'http://x/cb', '974', 'a@b.qa']],
]);

it('T-077 dates the transaction in Qatar time', function () {
    expect(SadadCheckoutBuilder::qatarDate(CarbonImmutable::parse('2026-10-09T21:30:00Z')))->toBe('2026-10-10');
});

it('T-042 never puts the secret in the request and falls back to a domain e-mail', function () {
    $req = SadadCheckoutBuilder::build('1664851', SADAD_SECRET, 'QTR-ABC123DEF456', '150', 'example.qa', 'https://qcamel.test/payments/sadad/callback', '97455512345', SadadCheckoutBuilder::fallbackEmail('example.qa'),
        CarbonImmutable::parse('2026-10-09T10:00:00Z'), [['name' => 'تذكرة VIP', 'price' => '150', 'quantity' => 1]]);

    expect($req['url'])->toBe('https://sadadqa.com/webpurchase')
        ->and($req['fields']['EMAIL'])->toBe('noreply@example.qa')
        ->and($req['fields']['TXN_AMOUNT'])->toBe('150.00')
        ->and($req['fields']['signature'])->toMatch('/^[0-9A-F]{64}$/')
        ->and($req['fields']['productdetail[0][itemname]'])->toBe('تذكرة VIP')
        ->and(json_encode($req, JSON_UNESCAPED_UNICODE))->not->toContain(SADAD_SECRET);
});

$callback = [
    'MID' => '1664851', 'ORDERID' => 'QTR-ABC123DEF456', 'RESPCODE' => '3', 'RESPMSG' => 'Txn Success', 'TXNAMOUNT' => '150.00',
    'STATUS' => 'TXN_SUCCESS', 'transaction_number' => 'SD2883696582255', 'transaction_status' => '3', 'website_ref_no' => 'QTR-ABC123DEF456', 'issandboxmode' => '1',
];
$pinned = '6082cc9b43e1c98b95f4911c9b696810cfa912f4a88e306217500a144bf26b8a';

it('T-078 verifies the pinned callback checksum, case-insensitively', function () use ($callback, $pinned) {
    expect(SadadSigner::verifyChecksum($callback + ['checksumhash' => $pinned], SADAD_SECRET))->toBeTrue()
        ->and(SadadSigner::verifyChecksum($callback + ['checksumhash' => strtoupper($pinned)], SADAD_SECRET))->toBeTrue();
});

it('T-078 rejects tampering, a wrong secret and a missing hash', function () use ($callback, $pinned) {
    expect(SadadSigner::verifyChecksum(array_merge($callback, ['TXNAMOUNT' => '1.00', 'checksumhash' => $pinned]), SADAD_SECRET))->toBeFalse()
        ->and(SadadSigner::verifyChecksum($callback + ['checksumhash' => $pinned], 'other'))->toBeFalse()
        ->and(SadadSigner::verifyChecksum($callback, SADAD_SECRET))->toBeFalse()
        ->and(SadadSigner::verifyChecksum($callback + ['checksumhash' => ''], SADAD_SECRET))->toBeFalse();
});

it('T-078 stringifies JSON webhook values like PHP (numbers as-is, null empty)', function () {
    $webhook = ['isTestMode' => 1, 'merchantId' => 1664851, 'message' => 'success', 'transactionNumber' => 'SD1', 'transactionStatus' => 3, 'txnAmount' => 150, 'websiteRefNo' => 'QTR-ABC123DEF456', 'invoiceNumber' => null];
    $strings = array_map(fn ($v) => $v === null ? '' : (string) $v, $webhook);
    ksort($strings, SORT_STRING);
    $hash = hash('sha256', SADAD_SECRET.implode('', $strings));

    expect(SadadSigner::verifyChecksum($webhook + ['checksumhash' => $hash], SADAD_SECRET))->toBeTrue();
});

it('T-079 normalises callback and webhook payloads', function () {
    $a = NormalizedPayment::from(['ORDERID' => 'QTR-ABC123DEF456', 'transaction_status' => '3', 'transaction_number' => 'SD1', 'TXNAMOUNT' => '150.00', 'issandboxmode' => '1']);
    expect($a->orderRef)->toBe('QTR-ABC123DEF456')->and($a->status)->toBe('success')->and($a->amount)->toBe(150.0)->and($a->sandbox)->toBeTrue();

    expect(NormalizedPayment::from(['websiteRefNo' => 'QTR-ABC123DEF456', 'transactionStatus' => '7'])->status)->toBe('failed')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'transaction_status' => '2'])->status)->toBe('failed')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'transaction_status' => '1'])->status)->toBe('in_progress')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'transaction_status' => '9'])->status)->toBe('unknown')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'STATUS' => 'TXN_SUCCESS'])->status)->toBe('success')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'STATUS' => 'TXN_FAILURE'])->status)->toBe('failed')
        ->and(NormalizedPayment::from(['ORDER_ID' => 'QTR-ABC123DEF456', 'isTestMode' => '0'])->sandbox)->toBeFalse()
        ->and(NormalizedPayment::from(['ORDERID' => "'; DROP TABLE orders;--"]))->toBeNull();
});

it('T-081 reads Sadad API rows: success wins, statuses mapped, other orders ignored', function () {
    $ref = 'QTR-ABC123DEF456';
    $row = fn (int $status, $amount = '150.00', ?string $r = null, string $inv = 'SD9') => ['transactionstatusId' => $status, 'amount' => $amount, 'website_ref_no' => $r ?? $ref, 'invoicenumber' => $inv];

    expect(SadadApiClient::summarise([$row(2), $row(3)], $ref, true)->kind)->toBe('success')
        ->and(SadadApiClient::summarise([$row(4)], $ref, false)->kind)->toBe('failed')
        ->and(SadadApiClient::summarise([$row(7)], $ref, false)->kind)->toBe('failed')
        ->and(SadadApiClient::summarise([$row(5)], $ref, false)->kind)->toBe('in_progress')
        ->and(SadadApiClient::summarise([$row(3, 'abc')], $ref, false)->kind)->toBe('not_found')
        ->and(SadadApiClient::summarise([$row(3, '150', 'QTR-OTHER0000000')], $ref, false)->kind)->toBe('not_found')
        ->and(SadadApiClient::summarise([], $ref, false)->kind)->toBe('not_found');
});
