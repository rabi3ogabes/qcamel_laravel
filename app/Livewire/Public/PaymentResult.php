<?php

namespace App\Livewire\Public;

use App\Domain\Booking\Queries\PublicOrderQueries;
use App\Domain\Payments\PaymentCheckThrottle;
use App\Domain\Payments\PaymentProcessor;
use App\Domain\Shared\Actor;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Support\PublicSession;
use App\Support\Text;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * F-PUB-063…069 / R-118: poll the public status every 3 s, ask the server to
 * verify with Sadad immediately and then every ≥ 12 s, verify a `failed`
 * order exactly once per round (late-payment rescue), stop at 120 s.
 */
#[Layout('components.layouts.public', ['surface' => 'white', 'track' => false])]
class PaymentResult extends Component
{
    #[Locked]
    public ?string $reference = null;

    #[Locked]
    public string $phase = 'checking'; // checking | failed | delayed | not_found

    #[Locked]
    public ?array $order = null;

    #[Locked]
    public float $roundStartedAt = 0;

    #[Locked]
    public ?float $lastVerifyAt = null;

    #[Locked]
    public bool $failedVerified = false;

    public function mount()
    {
        foreach (['ref', 'ORDERID', 'order'] as $key) {
            $value = request()->query($key);
            if (is_string($value) && ($ref = Text::reference(trim($value))) !== null) {
                $this->reference = $ref;
                break;
            }
        }
        $this->reference ??= PublicSession::latest();
        if ($this->reference === null) {
            $this->phase = 'not_found';

            return null;
        }
        $this->roundStartedAt = microtime(true);

        return $this->tick();
    }

    private function load(): ?array
    {
        return app(PublicOrderQueries::class)->status([$this->reference])[0] ?? null;
    }

    private function verify(): void
    {
        $this->lastVerifyAt = microtime(true);
        try {
            PaymentController::verifyReference($this->reference, Actor::current(), app(PaymentProcessor::class), app(PaymentCheckThrottle::class));
        } catch (Throwable) {
            // silent retry on the next tick (F-PUB-064)
        }
    }

    public function tick()
    {
        if ($this->phase !== 'checking' || $this->reference === null) {
            return null;
        }
        $order = $this->load();
        if ($order === null) {
            $this->phase = 'not_found';

            return null;
        }
        $status = $order['payment_status'];
        $isSadad = $order['payment_method'] === 'sadad';
        $mayBePaid = $status === 'pending' || ($status === 'failed' && ! $this->failedVerified);
        $due = $this->lastVerifyAt === null || microtime(true) - $this->lastVerifyAt >= (int) config('qcamelmc.payments.result_verify_seconds', 12);

        if ($isSadad && $mayBePaid && $due) {
            if ($status === 'failed') {
                $this->failedVerified = true;
            }
            $this->verify();
            $order = $this->load() ?? $order;
            $status = $order['payment_status'];
        }
        $this->order = $order;

        if ($status === 'confirmed') {
            PublicSession::remember($this->reference);
            PublicSession::clearCart();

            return $this->redirectRoute('confirmation', ['reference' => $this->reference]);
        }
        if ($order['payment_method'] === 'cash_pos') {
            PublicSession::remember($this->reference);

            return $this->redirectRoute('confirmation', ['reference' => $this->reference]);
        }
        if ($status === 'cancelled' || ($status === 'failed' && $this->failedVerified) || ($status === 'failed' && ! $isSadad)) {
            $this->phase = 'failed';

            return null;
        }
        if (microtime(true) - $this->roundStartedAt >= (int) config('qcamelmc.payments.result_timeout_seconds', 120)) {
            $this->phase = 'delayed';
        }

        return null;
    }

    public function checkAgain()
    {
        $this->phase = 'checking';
        $this->roundStartedAt = microtime(true);
        $this->lastVerifyAt = null;
        $this->failedVerified = false;

        return $this->tick();
    }

    /** F-PUB-068: reopen the payment page for a pending Sadad booking. */
    public function payAgain()
    {
        if (! $this->canResume()) {
            $this->dispatch('toast', type: 'error', message: __('errors.not_payable'));

            return null;
        }
        $key = 'pay-again:'.request()->ip().'|'.$this->reference;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('toast', type: 'error', message: __('errors.too_many_requests'));

            return null;
        }
        RateLimiter::hit($key, 60);

        return $this->redirectRoute('payment.redirect', ['reference' => $this->reference]);
    }

    public function canResume(): bool
    {
        return in_array($this->phase, ['delayed', 'failed'], true)
            && ($this->order['payment_method'] ?? null) === 'sadad'
            && ($this->order['payment_status'] ?? null) === 'pending';
    }

    public function render()
    {
        return view('livewire.public.payment-result', [
            'elapsed' => $this->roundStartedAt ? (int) (microtime(true) - $this->roundStartedAt) : 0,
        ])->title(__('public.result.'.$this->phase.'.title'));
    }
}
