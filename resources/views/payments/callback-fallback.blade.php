{{-- F-PAY-024 / T-069: shown after the Sadad callback when no safe return base is known. --}}
@extends('errors.layout')

@section('code', '✓')
@section('title', __('public.callback.title'))
@section('hint', __('public.callback.hint'))

@section('actions')
    @if($reference)
        <div class="w-full">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-ink-soft">{{ __('public.callback.reference') }}</p>
            <p dir="ltr" class="mt-1 font-mono text-lg font-extrabold text-ink">{{ $reference }}</p>
        </div>
        <a href="{{ url('/payment/result?ref='.rawurlencode($reference)) }}" class="inline-flex h-12 items-center gap-2 rounded-full bg-gold-400 px-6 font-bold text-ink shadow-soft transition duration-500 hover:bg-gold-300 active:scale-[.98]">
            {{ __('public.callback.view_status') }}
        </a>
    @endif
@endsection
