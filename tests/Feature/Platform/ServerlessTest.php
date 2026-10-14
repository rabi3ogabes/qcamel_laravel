<?php

use App\Domain\Payments\Sadad\ApiResult;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Jobs\ProcessSadadNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;

/*
| Serverless hosting (Vercel): no resident worker or scheduler.
*/

beforeEach(fn () => configureSadad());

function sadadWebhookOrder(): array
{
    $t = openEvent();
    $order = placeOrder([[$t['normal'], 1]], 'sadad');
    test()->mock(SadadApiClient::class, fn ($m) => $m->shouldReceive('lookupTransaction')->andReturn(
        ApiResult::success('SD-SLS', (float) $order->total_amount, $order->booking_reference, true)
    ));

    return [$order, ['websiteRefNo' => $order->booking_reference, 'transactionStatus' => 3, 'transactionNumber' => 'SD-SLS']];
}

it('queues the Sadad webhook for a worker on normal hosts', function () {
    [$order, $body] = sadadWebhookOrder();
    config(['qcamelmc.serverless.enabled' => false]);
    Bus::fake([ProcessSadadNotification::class]);

    $this->postJson('/payments/sadad/webhook', $body)->assertOk();

    Bus::assertDispatched(ProcessSadadNotification::class);
    expect($order->fresh()->payment_status->value)->toBe('pending');
});

it('confirms the payment inside the webhook request on serverless hosts', function () {
    [$order, $body] = sadadWebhookOrder();
    config(['qcamelmc.serverless.enabled' => true]);

    $this->postJson('/payments/sadad/webhook', $body)->assertOk()->assertExactJson(['status' => 'success']);

    expect($order->fresh()->payment_status->value)->toBe('confirmed')
        ->and($order->fresh()->payment_id)->toBe('SD-SLS');
});

it('runs the scheduler and drains the queue from the cron endpoint, only with the secret', function () {
    config(['qcamelmc.serverless.cron_secret' => 'cron-test-secret', 'qcamelmc.serverless.cron_budget_seconds' => 20]);

    $this->getJson('/vercel/cron/minute')->assertStatus(401);
    $this->withToken('wrong')->getJson('/vercel/cron/minute')->assertStatus(401);

    $res = $this->withToken('cron-test-secret')->getJson('/vercel/cron/minute')->assertOk()->json();
    expect($res['ok'])->toBeTrue()->and($res)->toHaveKeys(['schedule', 'queue', 'seconds']);
});

it('refuses the cron endpoint when no secret is configured', function () {
    config(['qcamelmc.serverless.cron_secret' => null]);
    $this->withToken('')->getJson('/vercel/cron/minute')->assertStatus(401);
});

it('redirects storage and bootstrap caches to the temp directory the entrypoint provides (read-only host)', function () {
    $tmp = str_replace('\\', '/', sys_get_temp_dir()).'/qcamel-serverless-test-'.uniqid();
    @mkdir("$tmp/bootstrap/cache", 0775, true);
    copy(base_path('bootstrap/providers.php'), "$tmp/bootstrap/providers.php");

    // Same calls bootstrap/app.php makes, applied to the running app and reverted afterwards.
    $app = app();
    $origStorage = $app->storagePath();
    $origBootstrap = $app->bootstrapPath();
    try {
        $app->useStoragePath($tmp);
        $app->useBootstrapPath("$tmp/bootstrap");
        $norm = fn (string $p) => str_replace('\\', '/', $p);

        expect($norm($app->storagePath()))->toBe($tmp)
            ->and($norm($app->getCachedConfigPath()))->toBe("$tmp/bootstrap/cache/config.php")
            ->and($norm($app->getCachedRoutesPath()))->toBe("$tmp/bootstrap/cache/routes-v7.php")
            ->and($norm($app->getCachedPackagesPath()))->toBe("$tmp/bootstrap/cache/packages.php")
            ->and(file_exists($app->bootstrapPath('providers.php')))->toBeTrue();
    } finally {
        $app->useStoragePath($origStorage);
        $app->useBootstrapPath($origBootstrap);
        File::deleteDirectory($tmp);
    }
});
