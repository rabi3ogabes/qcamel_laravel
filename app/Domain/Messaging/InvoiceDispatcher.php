<?php

namespace App\Domain\Messaging;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Jobs\SendWhatsAppInvoice;
use App\Models\Order;
use App\Settings\AutomationSettings;
use App\Support\Audit;
use App\Support\QatarClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Server-side invoice batch engine (F-NTF-039…045, F-ADM-INV-008/009/024,
 * R-152…R-156, D-016, D-017, T-124). Runs on the scheduler; the admin
 * console only toggles it or triggers "send now". Locked, interval-aware,
 * marks rows before dispatching, spaces sends with random delays.
 */
class InvoiceDispatcher
{
    public function __construct(private readonly AutomationSettings $settings) {}

    /** Confirmed Sadad orders of the current season (base for tabs). */
    public static function scope(bool $archived = false): Builder
    {
        return Order::query()
            ->where('payment_method', 'sadad')
            ->where('payment_status', 'confirmed')
            ->whereHas('event', fn ($q) => $q->withTrashed()->where('is_archived', $archived));
    }

    public function isExcluded(Order $order): bool
    {
        $dates = $this->settings->invoice_excluded_dates ?? [];

        return $dates !== [] && in_array(QatarClock::dateOf($order->created_at), $dates, true);
    }

    /** F-ADM-INV-005 event-done rule (cut-off on the last day, multi-day aware). */
    public static function eventDone(Order $order): bool
    {
        return $order->event ? $order->event->isExpired('sales') : true;
    }

    /** @return Collection<int, Order> */
    public function candidates(int $limit): Collection
    {
        $max = (int) config('qcamelmc.notifications.max_attempts', 2);

        return self::scope()
            ->whereNull('n8n_response_message')
            ->where('send_attempt_count', '<', $max)
            ->with('event')
            ->orderBy('created_at')
            ->limit($limit * 2)
            ->get()
            ->reject(fn (Order $o) => self::eventDone($o) || $this->isExcluded($o))
            ->values();
    }

    /**
     * @return array{message:string, batch_size?:int, total_quantity?:int, seconds_remaining?:int, next_send_in_seconds?:int, dispatched?:list<string>}
     */
    public function run(bool $manual = false, ?Actor $actor = null): array
    {
        if (! $manual && ! $this->settings->invoice_auto_send_enabled) {
            return ['message' => 'Auto-send disabled'];
        }
        if (! $this->settings->whatsappUrl()) {
            return ['message' => 'No webhook URL configured'];
        }
        $lock = Cache::lock('invoice-dispatch', 120);
        if (! $lock->get()) {
            return ['message' => 'Another batch is running'];
        }
        try {
            $interval = max((int) config('qcamelmc.notifications.interval_min_seconds', 10), (int) $this->settings->auto_invoice_interval_seconds);
            $last = $this->settings->last_invoice_sent_at ? \Carbon\CarbonImmutable::parse($this->settings->last_invoice_sent_at) : null;
            if (! $manual && $last && $last->addSeconds($interval)->isFuture()) {
                return ['message' => 'Interval not reached yet', 'seconds_remaining' => (int) round(now()->diffInSeconds($last->addSeconds($interval)))];
            }

            $min = max(1, (int) $this->settings->invoice_batch_min);
            $max = max($min, (int) $this->settings->invoice_batch_max);
            $candidates = $this->candidates($max);
            if ($candidates->isEmpty()) {
                $this->touch();

                return ['message' => 'No orders to send'];
            }
            $size = min($max, max($min, $candidates->count()));
            $batch = $candidates->take($size);
            $totalQuantity = (int) $batch->sum('quantity');

            $dispatched = [];
            $delay = 0;
            $dMin = max(1, (int) $this->settings->invoice_send_delay_min);
            $dMax = max($dMin, (int) $this->settings->invoice_send_delay_max);
            foreach ($batch->values() as $i => $order) {
                // Mark "sending" + count the attempt BEFORE dispatching, under a skip-locked row lock.
                $claimed = DB::transaction(function () use ($order) {
                    $row = Order::query()->whereKey($order->id)->whereNull('n8n_response_message')->lockForUpdate()->skipLockedIfSupported()->first();
                    if (! $row) {
                        return false;
                    }
                    $row->forceFill(['n8n_response_message' => Order::SENDING_WHATSAPP, 'n8n_responded_at' => null, 'automation_status' => 'sending_whatsapp', 'send_attempt_count' => $row->send_attempt_count + 1])->save();

                    return true;
                });
                if (! $claimed) {
                    continue;
                }
                if ($i > 0) {
                    $delay += random_int($dMin, $dMax); // R-154 between consecutive sends, not after the last
                }
                SendWhatsAppInvoice::dispatch($order->id, $totalQuantity)->delay(now()->addSeconds($delay));
                $dispatched[] = $order->booking_reference;
            }
            $this->touch();
            Audit::log(ActivityType::InvoiceDispatch, __('invoices.batch_audit', ['n' => count($dispatched)]), null, ['batch' => $dispatched, 'manual' => $manual], $actor ?? Actor::system());

            return ['message' => 'Dispatched '.count($dispatched).'/'.$size.' invoices', 'batch_size' => count($dispatched), 'total_quantity' => $totalQuantity, 'dispatched' => $dispatched, 'next_send_in_seconds' => $interval];
        } finally {
            $lock->release();
        }
    }

    private function touch(): void
    {
        $this->settings->last_invoice_sent_at = now()->toIso8601String();
        $this->settings->save();
    }

    /** Seconds until the next automatic run (console countdown). */
    public function secondsToNextRun(): ?int
    {
        if (! $this->settings->invoice_auto_send_enabled) {
            return null;
        }
        $interval = max(10, (int) $this->settings->auto_invoice_interval_seconds);
        if (! $this->settings->last_invoice_sent_at) {
            return 0;
        }
        $next = \Carbon\CarbonImmutable::parse($this->settings->last_invoice_sent_at)->addSeconds($interval);

        return max(0, (int) now()->diffInSeconds($next, false));
    }
}
