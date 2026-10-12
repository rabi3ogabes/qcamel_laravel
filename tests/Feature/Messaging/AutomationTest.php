<?php

use App\Domain\Messaging\AutomationRelay;
use App\Domain\Messaging\Drivers\N8nWebhookDriver;
use App\Domain\Messaging\N8nPayloads;
use App\Domain\Payments\Actions\ConfirmPaymentAction;
use App\Domain\Tickets\QrService;
use App\Enums\Role;
use App\Models\Order;
use App\Settings\AutomationSettings;
use App\Settings\ContactSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| n8n automation, payloads, QR tools — T-043, T-044, T-097…T-103, T-106…T-109.
*/

function configureAutomation(array $overrides = []): void
{
    $s = app(AutomationSettings::class);
    foreach (array_merge(['webhook_url' => 'https://n8n.qcamel.test/webhook/wa', 'email_webhook_url' => 'https://n8n.qcamel.test/webhook/mail', 'webhook_enabled' => true, 'email_webhook_enabled' => true], $overrides) as $k => $v) {
        $s->{$k} = $v;
    }
    $s->save();
}

beforeEach(fn () => Storage::fake((string) config('qcamelmc.qr.disk', 'qr')));

it('T-043 posts the order-created payload for a cash web order, nothing for unpaid online orders', function () {
    configureAutomation();
    configureSadad();
    Http::fake(['n8n.qcamel.test/*' => Http::response(['ok' => true])]);
    $t = openEvent();

    $cash = placeOrder([[$t['normal'], 1]], 'cash_pos');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://n8n.qcamel.test/webhook/wa' && $r['bookingReference'] === $cash->booking_reference && isset($r['ticketHolders'], $r['timestamp']));

    $count = count(Http::recorded());
    placeOrder([[$t['normal'], 1]], 'sadad');
    expect(count(Http::recorded()))->toBe($count);
});

it('T-044 renders one QR PNG per ticket without ever failing the order', function () {
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 2]]);

    foreach ($order->holders as $h) {
        Storage::disk((string) config('qcamelmc.qr.disk', 'qr'))->assertExists($h->qr_code.'.png');
        expect($h->fresh()->qr_image_url)->toEndWith($h->qr_code.'.png');
    }
});

it('T-097 relays to n8n and reports configuration and upstream errors without throwing', function () {
    $t = openEvent();
    $order = posOrder([[$t['normal'], 1]]);
    $relay = app(AutomationRelay::class);

    expect($relay->send('send_invoice', $order))->toMatchArray(['success' => false, 'code' => 'webhook_not_configured']);

    configureAutomation();
    $relay = app(AutomationRelay::class);
    Http::fake(['n8n.qcamel.test/webhook/wa' => Http::response(['msg' => 'not found'], 404)]);
    expect($relay->send('send_invoice', $order))->toMatchArray(['success' => false, 'error' => 'Webhook not found or inactive', 'status' => 404]);

    Http::fake(['n8n.qcamel.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused')]);
    expect($relay->send('send_invoice', $order)['success'])->toBeFalse();
});

it('T-097 refuses non-object relay bodies', function () {
    $this->actingAs(staffUser(Role::Admin));
    $this->call('POST', '/api/v1/admin/relay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '[1,2]')->assertStatus(400);
});

it('T-098 sends e-mails to the e-mail webhook and tickets to the main webhook', function () {
    configureAutomation();
    Http::fake(['n8n.qcamel.test/*' => Http::response(['ok' => true])]);
    $t = openEvent();
    $order = posOrder([[$t['normal'], 1]]);
    $relay = app(AutomationRelay::class);

    $relay->send('send_email', $order);
    $relay->send('send_ticket', $order);

    $urls = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->values()->all();
    expect($urls)->toContain('https://n8n.qcamel.test/webhook/mail')->toContain('https://n8n.qcamel.test/webhook/wa');
});

it('T-099 marks the order as sending only when the automation accepted it', function () {
    configureAutomation();
    $t = openEvent();
    $order = posOrder([[$t['normal'], 1]]);

    Http::fake(['n8n.qcamel.test/*' => Http::sequence()->push('nope', 500)->push(['ok' => true])]);
    $before = $order->fresh()->n8n_response_message;
    app(AutomationRelay::class)->send('send_invoice', $order);
    expect($order->fresh()->n8n_response_message)->toBe($before);

    app(AutomationRelay::class)->send('send_invoice', $order);
    expect($order->fresh()->n8n_response_message)->toBe(Order::SENDING_WHATSAPP)->and($order->fresh()->n8n_responded_at)->toBeNull();
});

it('T-100 builds the paid payload from the database', function () {
    $t = openEvent();
    configureSadad();
    $order = placeOrder([[$t['vip'], 1]], 'sadad');
    app(ConfirmPaymentAction::class)->execute($order->booking_reference, 'SD-42');
    $p = N8nPayloads::paymentConfirmed($order->fresh());

    expect($p['action'])->toBe('payment_confirmed')->and($p['sadad_response']['transaction_number'])->toBe('SD-42')
        ->and($p)->toHaveKeys(['customer', 'event', 'ticket_holders', 'timestamp'])
        ->and((string) $p['ticket_holders'][0]['ticket_price'])->toContain('200');
});

it('T-106 builds the order-created payload with international phones and QR data', function () {
    $s = app(ContactSettings::class);
    $s->admin_phone = '55500000';
    $s->save();
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]]);
    $p = N8nPayloads::orderCreated($order);

    expect($p['bookingReference'])->toBe($order->booking_reference)
        ->and($p['adminPhone'])->toBe('97455500000')
        ->and($p['customer']['phone'])->toStartWith('974')
        ->and($p['ticketHolders'][0]['qr_code'])->toBe($order->holders()->first()->qr_code);
});

it('T-108 never throws when posting and refuses non-http URLs', function () {
    $driver = app(N8nWebhookDriver::class);

    expect($driver->post('ftp://example.org/x', []))->toMatchArray(['ok' => false, 'error' => 'invalid_webhook_url']);
    Http::fake(['example.org/*' => Http::response('bad', 502)]);
    expect($driver->post('https://example.org/hook', ['a' => 1]))->toMatchArray(['ok' => false, 'status' => 502]);
});

it('T-109 trims settings and turns off disabled automations', function () {
    configureAutomation(['webhook_url' => '  https://n8n.qcamel.test/webhook/wa  ', 'email_webhook_enabled' => false]);
    $s = app(AutomationSettings::class);

    expect($s->whatsappUrl())->toBe('https://n8n.qcamel.test/webhook/wa')->and($s->emailUrl())->toBeNull();
});

it('T-101 / T-102 / T-103 generates, backfills and regenerates QR images safely', function () {
    $this->actingAs(staffUser(Role::Admin));
    $this->postJson('/api/v1/admin/qr/generate', ['text' => 'QTR-X-TKT01', 'filename' => 'QTR-X-TKT01'])->assertOk()->assertJsonStructure(['url']);
    foreach (['../x', 'a/b', '', 'x.png', ' '] as $bad) {
        $this->postJson('/api/v1/admin/qr/generate', ['text' => 'QTR-X', 'filename' => $bad])->assertStatus(400);
    }

    $t = openEvent();
    $order = posOrder([[$t['normal'], 2]]);
    $this->actingAs(staffUser(Role::Admin));
    $codes = $order->holders()->pluck('qr_code')->all();
    $order->holders()->update(['qr_image_url' => null]);

    $res = $this->postJson('/api/v1/admin/qr/backfill')->assertOk()->json();
    expect($res['updated'])->toBe(2)->and($order->holders()->pluck('qr_code')->all())->toBe($codes);

    $this->postJson('/api/v1/admin/qr/regenerate', ['booking_reference' => $order->booking_reference])->assertOk()->assertJson(['updated' => 2, 'total' => 2]);
    $this->postJson('/api/v1/admin/qr/regenerate', ['booking_reference' => 'QTR-1-1-2026-AAAAAAAAAAAA'])->assertStatus(404);
    $this->postJson('/api/v1/admin/qr/regenerate', ['booking_reference' => 'bad ref!'])->assertStatus(400);
});
