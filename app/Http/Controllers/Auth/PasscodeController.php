<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Staff\PasscodeAuthenticator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F-AUTH-009 / F-STF-003 team passcode exchange (always HTTP 200, T-091). */
class PasscodeController extends Controller
{
    public function __invoke(Request $request, PasscodeAuthenticator $auth): JsonResponse
    {
        $passcode = $request->isJson() ? ($request->json('passcode')) : $request->input('passcode');
        $result = $auth->attempt($passcode, $request, $request->input('device_name'));
        if (($result['ok'] ?? false) === true) {
            $request->session()->regenerate();
            $result['redirect'] = self::safeNext($request->input('next')) ?? route('staff.hub');
        }

        return response()->json($result);
    }

    /** F-AUTH-006: only safe relative paths (starts with "/" and not "//"). */
    public static function safeNext(mixed $next): ?string
    {
        return is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_starts_with($next, '/\\') ? $next : null;
    }
}
