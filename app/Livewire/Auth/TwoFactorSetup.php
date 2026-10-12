<?php

namespace App\Livewire\Auth;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * U-AUTH-01: mandatory TOTP enrolment for admin-panel roles. Step 1 shows
 * the QR + manual key, step 2 confirms a code, step 3 shows recovery codes.
 */
class TwoFactorSetup extends Component
{
    #[Locked]
    public string $step = 'start'; // start | scan | done

    public string $code = '';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user?->two_factor_confirmed_at) {
            $this->step = 'done';
        } elseif ($user?->two_factor_secret) {
            $this->step = 'scan';
        }
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(auth()->user());
        $this->step = 'scan';
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);
        try {
            $confirm(auth()->user(), $this->code);
        } catch (ValidationException) {
            $this->addError('code', __('auth.two_factor_invalid'));

            return;
        }
        $this->step = 'done';
        $this->dispatch('toast', type: 'success', message: __('auth.two_factor_enabled'));
    }

    public function render()
    {
        $user = auth()->user()->fresh();

        return view('livewire.auth.two-factor-setup', [
            'qr' => $this->step === 'scan' && $user->two_factor_secret ? $user->twoFactorQrCodeSvg() : null,
            'secret' => $this->step === 'scan' && $user->two_factor_secret ? decrypt($user->two_factor_secret) : null,
            'codes' => $this->step === 'done' && $user->two_factor_recovery_codes ? $user->recoveryCodes() : [],
            'home' => $user->canAccessPanel(filament()->getPanel('admin')) ? url('/admin') : route('staff.hub'),
        ]);
    }
}
