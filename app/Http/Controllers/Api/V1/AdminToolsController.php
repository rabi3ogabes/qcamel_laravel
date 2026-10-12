<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Actions\OrderStatusActions;
use App\Domain\Booking\Actions\RecycleBinActions;
use App\Domain\Messaging\AutomationRelay;
use App\Domain\Payments\Sadad\SadadApiClient;
use App\Domain\Payments\Sadad\SadadDiagnose;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\QrService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin tool endpoints (T-096…T-103, F-NTF-028/029, F-PAY-051/056):
 * session/token auth, admin permission, server-built payloads only.
 */
class AdminToolsController extends Controller
{
    public function generateQr(Request $request, QrService $qr): JsonResponse
    {
        return response()->json(['success' => true, 'url' => $qr->generate((string) $request->input('text', ''), (string) $request->input('filename', ''))]);
    }

    public function backfillQr(QrService $qr): JsonResponse
    {
        $r = $qr->backfill((int) config('qcamelmc.qr.backfill_batch', 100));

        return response()->json($r + ['message' => "Created {$r['updated']} QR images".($r['remaining'] ? ' — run it again to continue' : '')]);
    }

    public function regenerateQr(Request $request, QrService $qr): JsonResponse
    {
        $r = $qr->regenerateBooking((string) $request->input('booking_reference', ''));

        return response()->json($r + ['message' => "Regenerated {$r['updated']} QR images for booking ".$request->input('booking_reference')]);
    }

    /**
     * T-097…T-100 relay: the browser sends {action|notify, booking_reference|order_id, holder_id?};
     * the server builds the payload. Non-object bodies → 400 invalid_payload.
     */
    public function relay(Request $request, AutomationRelay $relay): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true);
        if (! is_array($body) || array_is_list($body)) {
            throw new DomainError('invalid_payload', 400, 'Payload must be a JSON object');
        }
        $action = ($body['notify'] ?? null) === 'payment_confirmed' ? 'payment_confirmed' : (string) ($body['action'] ?? 'send_invoice');
        if (! in_array($action, AutomationRelay::ACTIONS, true)) {
            $action = 'send_invoice';
        }
        $order = null;
        if (! empty($body['order_id']) && is_string($body['order_id'])) {
            $order = Order::query()->find($body['order_id']);
        } elseif (array_key_exists('booking_reference', $body)) {
            $ref = Text::reference($body['booking_reference']);
            if (! $ref) {
                throw new DomainError('invalid_reference', 400, 'Invalid booking reference');
            }
            $order = Order::query()->where('booking_reference', $ref)->first();
        }
        if (! $order) {
            throw DomainError::notFound();
        }
        $holder = ! empty($body['holder_id']) ? $order->holders()->whereKey($body['holder_id'])->first() : null;
        $result = $relay->send($action, $order, $holder, Actor::current());
        if (($result['code'] ?? null) === 'webhook_not_configured') {
            return response()->json(['success' => false, 'code' => 'webhook_not_configured', 'error' => $result['error'], 'hint' => $result['hint']], 400);
        }

        return response()->json($result);
    }

    public function diagnose(SadadDiagnose $diagnose): JsonResponse
    {
        return response()->json($diagnose->run());
    }

    /** F-PAY-056 transaction details. */
    public function transaction(Request $request, SadadApiClient $api): JsonResponse
    {
        $orderId = $request->input('orderId');
        $txn = trim((string) $request->input('transactionno', ''));
        if (! $orderId && $txn === '') {
            return response()->json(['success' => false, 'error' => 'يجب تحديد الطلب أو رقم عملية سداد'], 400);
        }
        if ($txn === '') {
            $order = Order::query()->find($orderId);
            if (! $order) {
                return response()->json(['success' => false, 'error' => __('sadad.order_missing')], 404);
            }
            if (! $order->isSadad()) {
                return response()->json(['success' => false, 'error' => __('sadad.not_sadad')], 400);
            }
            $txn = (string) $order->payment_id;
            if ($txn === '') {
                return response()->json(['success' => false, 'error' => __('sadad.no_txn')], 400);
            }
        }
        $res = $api->transactionDetails(mb_substr($txn, 0, 100));

        return response()->json($res, isset($res['status']) && ! ($res['success'] ?? false) ? $res['status'] : 200);
    }

    public function cancel(Order $order, Request $request, OrderStatusActions $actions): JsonResponse
    {
        $result = $actions->cancel($order, $request->input('note'), Actor::current());

        return response()->json(['success' => true, 'result' => $result, 'order_id' => $order->id]);
    }

    public function recount(RecycleBinActions $bin): JsonResponse
    {
        return response()->json(['success' => true, 'changed' => $bin->recount(Actor::current())]);
    }
}
