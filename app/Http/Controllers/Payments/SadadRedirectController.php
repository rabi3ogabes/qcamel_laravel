<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\Sadad\SadadCheckoutBuilder;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Text;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * F-PUB-060 / F-PAY-018 / T-114: server-rendered auto-submitting Sadad form.
 * The signed field set comes from the session (just created) or is re-signed
 * from frozen order_items while the hold is valid (pay again, R-119).
 */
class SadadRedirectController extends Controller
{
    public function __invoke(Request $request, string $reference, SadadCheckoutBuilder $builder): Response
    {
        $reference = Text::reference($reference) ?? abort(404);
        $payment = $request->session()->pull('sadad_payment.'.$reference);

        if (! is_array($payment)) {
            $order = Order::query()->where('booking_reference', $reference)->first();
            if (! $order || ! $order->isSadad() || $order->payment_status->value !== 'pending' || ! $order->holdIsValid() || ! app(SadadConfig::class)->isConfigured()) {
                return redirect()->route('payment.result', ['ref' => $reference]);
            }
            try {
                $payment = $builder->forOrder($order, 'restart', $request->ip());
            } catch (Throwable) {
                return redirect()->route('payment.result', ['ref' => $reference]);
            }
        }
        if (! SadadConfig::isAllowedCheckoutUrl((string) ($payment['url'] ?? ''))) {
            abort(400, 'Refusing to send payment details to an address that is not Sadad');
        }

        return response()->view('payments.redirect', ['payment' => $payment, 'reference' => $reference])
            ->header('Cache-Control', 'no-store');
    }
}
