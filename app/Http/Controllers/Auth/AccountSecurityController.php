<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** U-AUTH-01 2FA enrolment page and D-001 forced password rotation. */
class AccountSecurityController extends Controller
{
    public function twoFactor(): View
    {
        return view('auth.two-factor-setup');
    }

    public function password(): View
    {
        return view('auth.force-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()]]);
        $request->user()->forceFill(['password' => $request->input('password'), 'must_reset_password' => false])->save();

        return redirect()->intended($request->user()->canAccessPanel(filament()->getPanel('admin')) ? '/admin' : '/staff');
    }
}
