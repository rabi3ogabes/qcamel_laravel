<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Payments\PaymentCheckThrottle;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Payments\Sadad\SadadCheckoutBuilder;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F-PAY-020 pay again (T-072) and F-PAY-044…046 verify (T-059, T-071). */
class PaymentController extends Controller
{
    public function verify(Request $request, PaymentProcessor $processor, PaymentCheckThrottle $throttle): JsonResponse
    {
        $ref = Text::reference($request->input('booking_reference'));
        if (! $ref) {
            throw new DomainError('invalid_reference', 400, 'Invalid booking reference');
        }

        return response()->json(self::verifyReference($ref, Actor::current(), $processor, $throttle), 200, ['Cache-Control' => 'no-store']);
    }

    /** Shared by the API and the Livewire result page. */
    public static function verifyReference(string $ref, Actor $actor, PaymentProcessor $processor, PaymentCheckThrottle $throttle): array
    {
        $order = Order::query()->where('booking_reference', $ref)->first();
        if (! $order) {
            throw DomainError::notFound();
        }
        if ($order->payment_status->value === 'confirmed') {
            return ['success' => true, 'checked' => false, 'payment_status' => 'confirmed'];
        }
        $isAdmin = $actor->can('orders.verify');
        // D-012: an admin never re-opens a final staff cancellation via verify.
        $allowed = $isAdmin ? ! $order->isFinalCancellation() : $throttle->claim($ref);
        if ($allowed) {
            $processor->process($ref, null, null, false, $isAdmin ? 'admin' : 'poll', $actor->userId());
        }

        return ['success' => true, 'checked' => $allowed, 'payment_status' => $order->fresh()->publicStatus()];
    }

    public function restart(Request $request, SadadCheckoutBuilder $builder, SadadConfig $config, ExpireHoldsAction $expire): JsonResponse
    {
        $ref = Text::reference($request->input('booking_reference'));
        if (! $ref) {
            throw new DomainError('invalid_reference', 400, 'Invalid booking reference');
        }
        $order = Order::query()->where('booking_reference', $ref)->first();
        if (! $order) {
            throw DomainError::notFound();
        }
        if (! $config->isConfigured()) {
            throw new DomainError('payment_unavailable', 503, 'Online payment is not available right now');
        }
        $expire->forEvent($order->event_id);
        $order->refresh();
        // R-119 / T-072: only pending sadad orders whose hold is still valid.
        if ($order->payment_status->value !== 'pending' || ! $order->isSadad() || ! $order->holdIsValid()) {
            throw new DomainError('not_payable', 409, 'This booking is not waiting for an online payment');
        }

        return response()->json(['success' => true, 'payment' => $builder->forOrder($order, 'restart', $request->ip())], 200, ['Cache-Control' => 'no-store']);
    }
}
