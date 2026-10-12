@php $isAr = app()->getLocale() !== 'en'; $nonce = \Illuminate\Support\Facades\Vite::cspNonce(); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('public.checkout.redirecting') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grain grid min-h-dvh place-items-center bg-cream px-6 font-sans text-ink antialiased">
    {{-- F-PUB-060 / F-PAY-018: server-signed field set, allow-listed Sadad host only --}}
    <main class="w-full max-w-sm text-center">
        <div class="bezel mx-auto">
            <div class="bezel-core p-8">
                <div class="relative mx-auto size-20">
                    <span class="absolute inset-0 animate-ping rounded-full bg-maroon-200/60"></span>
                    <span class="relative grid size-20 place-items-center rounded-full bg-maroon-600 text-white shadow-elegant">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg>
                    </span>
                </div>
                <h1 class="mt-6 text-xl font-extrabold">{{ __('public.checkout.redirecting') }}</h1>
                <p class="mt-2 font-mono text-sm text-ink-soft" dir="ltr">{{ $reference }}</p>

                <form id="sadad" method="post" action="{{ $payment['url'] }}" target="_self" class="mt-7">
                    @foreach(($payment['fields'] ?? []) as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ is_scalar($value) ? $value : json_encode($value) }}">
                    @endforeach
                    <noscript>
                        <button type="submit" class="h-12 w-full rounded-full bg-maroon-600 px-6 font-bold text-white">{{ $isAr ? 'متابعة إلى سداد' : 'Continue to Sadad' }}</button>
                    </noscript>
                    <button type="submit" id="manual" class="hidden h-12 w-full rounded-full bg-maroon-600 px-6 font-bold text-white">{{ $isAr ? 'متابعة إلى سداد' : 'Continue to Sadad' }}</button>
                </form>
                <a href="{{ route('payment.result', ['ref' => $reference]) }}" class="mt-4 inline-block text-xs font-bold text-ink-soft underline-offset-4 hover:underline">{{ __('public.open_status') }}</a>
            </div>
        </div>
    </main>
    <script nonce="{{ $nonce }}">
        (function () {
            var f = document.getElementById('sadad');
            setTimeout(function () { try { f.submit(); } catch (e) { document.getElementById('manual').classList.remove('hidden'); } }, 350);
            setTimeout(function () { document.getElementById('manual').classList.remove('hidden'); }, 4000);
        })();
    </script>
</body>
</html>
