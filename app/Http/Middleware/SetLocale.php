<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** F-PUB-093: ?lang= → session → Accept-Language; Arabic default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('qcamelmc.locales', ['ar', 'en']);
        $lang = $request->query('lang');
        if (is_string($lang) && in_array($lang, $supported, true)) {
            $request->session()->put('locale', $lang);
        }
        $locale = $request->session()->get('locale');
        if (! in_array($locale, $supported, true)) {
            $locale = 'ar';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
