<?php

namespace App\Jobs;

use App\Domain\Messaging\AutomationRelay;
use App\Events\OrderAutomationUpdated;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One invoice send of a batch (F-NTF-043): success = 2xx and
 * response.success !== false → final message + attempts reset (D-017 fix);
 * failure → back to the queue of candidates until the 2-attempt hold.
 */
class SendWhatsAppInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $orderId, public int $totalQuantity)
    {
        $this->onQueue('automation');
    }

    public function handle(AutomationRelay $relay): void
    {
        $order = Order::query()->find($this->orderId);
        if (! $order || $order->n8n_response_message !== Order::SENDING_WHATSAPP) {
            return; // manually handled / reset meanwhile
        }
        $res = $relay->send('auto_invoice', $order, totalQuantity: $this->totalQuantity, markSending: false);
        $json = $res['json'] ?? null;
        $accepted = ($res['success'] ?? false) && ! (is_array($json) && ($json['success'] ?? null) === false);
        if ($accepted) {
            $message = is_array($json) && is_string($json['message'] ?? null) && $json['message'] !== '' ? $json['message'] : Order::AUTO_SENT_MARKER;
            $order->forceFill(['n8n_response_message' => mb_substr($message, 0, 500), 'n8n_responded_at' => now(), 'automation_status' => 'delivered', 'send_attempt_count' => 0])->save();
        } else {
            $order->forceFill(['n8n_response_message' => null, 'n8n_responded_at' => null, 'automation_status' => 'failed'])->save();
        }
        event(new OrderAutomationUpdated($order));
    }
}
