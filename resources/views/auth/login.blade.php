@php
    $lock = (int) session('login_lock', 0);
    $left = session('attempts_left');
    $intended = (string) session('url.intended', '');
    $next = \App\Http\Controllers\Auth\PasscodeController::safeNext(request('next'));
    $passcodeOn = rescue(fn () => app(\App\Settings\FeatureFlagSettings::class)->passcode_login_enabled, true, false);
    $defaultTab = request('tab') === 'account' || $errors->has('email') || old('email') || str_contains($intended.(string) request('next'), '/admin') || ! $passcodeOn ? 'account' : 'passcode';
@endphp
<x-layouts.auth :title="__('auth.title')">
    {{-- F-AUTH-002 unified login card --}}
    <div x-data="loginCard({ tab: @js($defaultTab), lock: {{ $lock }}, left: @js(is_numeric($left) ? (int) $left : null) })" x-init="start()" class="bezel">
        <div class="bezel-core overflow-hidden !p-0">
            <div class="sadu relative bg-gradient-to-br from-maroon-700 to-maroon-600 px-7 pb-7 pt-8 text-center text-white">
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-white/12 text-gold-300 ring-1 ring-white/20"><x-glyph name="lock" size="26" /></span>
                <h1 class="mt-4 text-2xl font-extrabold">{{ __('auth.title') }}</h1>
                <p class="mt-1 text-sm text-white/75">{{ __('auth.subtitle') }}</p>
            </div>

            <div class="p-6 sm:p-7">
                @if($passcodeOn)
                    <div class="grid grid-cols-2 gap-1 rounded-full bg-ivory p-1 ring-1 ring-inset ring-bark/10" role="tablist">
                        <button type="button" role="tab" :aria-selected="tab === 'account'" @click="tab = 'account'"
                            :class="tab === 'account' ? 'bg-white text-ink shadow-soft ring-1 ring-bark/10' : 'text-ink-soft hover:text-ink'"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-full text-sm font-bold transition duration-300"><x-glyph name="shield" size="17" />{{ __('auth.tab_account') }}</button>
                        <button type="button" role="tab" :aria-selected="tab === 'passcode'" @click="tab = 'passcode'"
                            :class="tab === 'passcode' ? 'bg-white text-ink shadow-soft ring-1 ring-bark/10' : 'text-ink-soft hover:text-ink'"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-full text-sm font-bold transition duration-300"><x-glyph name="key" size="17" />{{ __('auth.tab_passcode') }}</button>
                    </div>
                @endif

                {{-- F-AUTH-013 lock banner --}}
                <div x-show="lock > 0" x-cloak class="mt-5 rounded-2xl bg-danger/[0.06] p-4 text-danger ring-1 ring-danger/20" role="alert">
                    <p class="flex items-center gap-2 font-extrabold"><x-glyph name="alert" size="18" />{{ __('auth.locked_title') }}</p>
                    <p class="mt-1 text-sm">{{ __('auth.locked_body', ['time' => '']) }}<span dir="ltr" class="font-mono font-bold" x-text="clock()"></span></p>
                </div>
                {{-- F-AUTH-016 attempts warning --}}
                <p x-show="lock === 0 && left !== null && left <= 2" x-cloak class="mt-5 flex items-center gap-2 rounded-2xl bg-gold-50 p-3 text-sm font-bold text-gold-800 ring-1 ring-gold-200">
                    <x-glyph name="info" size="16" /><span x-text="@js(__('auth.attempts_left', ['n' => '__N__'])).replace('__N__', left)"></span>
                </p>

                {{-- Account tab (Fortify POST /login) --}}
                <form x-show="tab === 'account'" method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" @submit="busy = true">
                    @csrf
                    @if($next)<input type="hidden" name="next" value="{{ $next }}">@endif
                    <x-ui.field :label="__('auth.email')" for="email" required error="email">
                        <x-ui.input id="email" name="email" type="email" dir="ltr" icon="mail" :value="old('email')" autocomplete="username" required autofocus :placeholder="__('auth.email_placeholder')" :invalid="$errors->has('email')" />
                    </x-ui.field>
                    <x-ui.field :label="__('auth.password_label')" for="password" required>
                        <div class="relative" x-data="{ show: false }">
                            <x-ui.input id="password" name="password" x-bind:type="show ? 'text' : 'password'" type="password" dir="ltr" icon="key" autocomplete="current-password" required placeholder="••••••••" />
                            <button type="button" @click="show = !show" class="absolute inset-y-0 end-0 grid w-11 place-items-center text-clay hover:text-ink" :aria-label="show ? 'hide' : 'show'">
                                <span x-show="!show"><x-glyph name="eye" size="18" /></span><span x-show="show" x-cloak><x-glyph name="eye-off" size="18" /></span>
                            </button>
                        </div>
                    </x-ui.field>
                    <label class="flex items-center gap-2 text-sm text-ink-soft"><input type="checkbox" name="remember" class="size-4 rounded border-bark/30 text-maroon-600 focus:ring-maroon-500"> {{ __('auth.remember') }}</label>
                    <button type="submit" :disabled="lock > 0 || busy"
                        class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-full bg-maroon-600 text-base font-bold text-white shadow-elegant transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-maroon-700 active:scale-[.98] disabled:opacity-60">
                        <template x-if="busy"><x-ui.spinner size="18" /></template>
                        <span x-text="lock > 0 ? @js(__('auth.locked_btn', ['time' => '__T__'])).replace('__T__', clock()) : (busy ? @js(__('auth.signing_in')) : @js(__('auth.sign_in')))"></span>
                    </button>
                    <div class="flex items-center justify-between gap-3 pt-1 text-xs">
                        <p class="text-ink-soft">{{ __('auth.account_hint') }}</p>
                    </div>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="block text-center text-sm font-bold text-maroon-600 underline-offset-4 hover:underline">{{ __('auth.forgot') }}</a>
                    @endif
                </form>

                {{-- Passcode tab (POST /staff/passcode → kiosk session, 10 days) --}}
                @if($passcodeOn)
                    <form x-show="tab === 'passcode'" x-cloak class="mt-6 space-y-4" @submit.prevent="passcode()">
                        <x-ui.field :label="__('auth.passcode_label')" for="passcode">
                            <x-ui.input id="passcode" x-model="code" type="password" dir="ltr" icon="key" autocomplete="off" placeholder="••••••••" />
                        </x-ui.field>
                        <x-ui.field :label="__('auth.device_name')" for="device_name">
                            <x-ui.input id="device_name" x-model="device" maxlength="60" icon="phone" :placeholder="__('auth.device_name_placeholder')" />
                        </x-ui.field>
                        <button type="submit" :disabled="lock > 0 || busy"
                            class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-full bg-ink text-base font-bold text-cream shadow-float transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-ink/90 active:scale-[.98] disabled:opacity-60">
                            <template x-if="busy"><x-ui.spinner size="18" /></template>
                            <span x-text="lock > 0 ? @js(__('auth.locked_btn', ['time' => '__T__'])).replace('__T__', clock()) : (busy ? @js(__('auth.verifying')) : @js(__('auth.open_page')))"></span>
                        </button>
                        <p class="text-center text-xs text-ink-soft">{{ __('auth.passcode_hint') }}</p>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <a href="{{ route('home') }}" class="mt-5 flex h-12 w-full items-center justify-center rounded-full text-sm font-bold text-ink-soft transition hover:bg-bark/5 hover:text-ink">{{ __('auth.back_home') }}</a>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginCard', (o) => ({
                tab: o.tab, lock: o.lock, left: o.left, busy: false, code: '', device: '',
                start() { setInterval(() => { if (this.lock > 0) this.lock--; }, 1000); },
                clock() { const s = Math.max(0, this.lock); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); },
                toast(type, message) { Alpine.store('toasts').push({ type, message }); },
                async passcode() {
                    if (this.lock > 0 || this.busy) return;
                    this.busy = true;
                    try {
                        const r = await fetch(@js(route('staff.passcode')), {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ passcode: this.code, device_name: this.device, next: @js($next) }),
                        });
                        const d = await r.json();
                        if (d.ok) { this.toast('success', @js(__('auth.page_unlocked'))); window.location.replace(d.redirect || '/staff'); return; }
                        if (d.unavailable) { this.toast('error', @js(__('auth.passcode_unavailable'))); }
                        else if (d.blocked) { this.lock = d.retry_after_seconds || 1800; this.toast('error', @js(__('auth.blocked_toast'))); }
                        else { if (typeof d.attempts_left === 'number') this.left = d.attempts_left; this.toast('error', @js(__('auth.passcode_wrong'))); }
                    } catch (e) { this.toast('error', @js(__('auth.passcode_error'))); }
                    this.busy = false;
                },
            }));
        });
    </script>
</x-layouts.auth>
