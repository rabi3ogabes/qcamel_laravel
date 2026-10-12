<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Staff\PasscodeAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** F-AUTH-025 / D-096: one logout that ends both the account and the kiosk session. */
class StaffLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        PasscodeAuthenticator::forget($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
