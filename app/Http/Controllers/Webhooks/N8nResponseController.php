<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\OrderAutomationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-NTF-037 n8n delivery callback (R-091, R-151, D-028): mandatory secret
 * (X-Webhook-Secret or Bearer, constant time), real 404, status stored,
 * attempts reset on a success reply (D-017).
 */
class N8nResponseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('qcamelmc.notifications.n8n_response_secret');
        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->bearerToken());
        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 401);
        }

        $ref = $request->input('booking_reference');
        $message = $request->input('message');
        if (! is_string($ref) || trim($ref) === '' || ! is_string($message) || trim($message) === '') {
            return response()->json(['success' => false, 'error' => 'Missing required fields: booking_reference and message'], 400);
        }
        $ref = trim($ref);
        $order = Text::isReference($ref) ? Order::query()->where('booking_reference', $ref)->first() : null;
        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Order not found with booking reference: '.$ref], 404);
        }
        $status = in_array($request->input('status'), ['success', 'failed'], true) ? $request->input('status') : null;
        $order->forceFill([
            'n8n_response_message' => mb_substr($message, 0, (int) config('qcamelmc.notifications.response_max_chars', 500)),
            'n8n_responded_at' => now(),
            'n8n_response_status' => $status,
            'automation_status' => $status === 'failed' ? 'failed' : 'delivered',
            'send_attempt_count' => $status === 'failed' ? $order->send_attempt_count : 0,
        ])->save();
        event(new OrderAutomationUpdated($order));

        return response()->json(['success' => true, 'message' => 'Order updated successfully', 'order_id' => $order->id, 'booking_reference' => $order->booking_reference]);
    }
}
