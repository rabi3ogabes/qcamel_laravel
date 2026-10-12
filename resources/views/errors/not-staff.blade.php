@extends('errors.layout')

@section('code', '403')
@section('title', __('public.errors.not_staff_title'))
@section('hint', __('public.errors.not_staff_hint'))
@section('actions')
    <form method="POST" action="{{ route('staff.logout') }}">
        @csrf
        <button type="submit" class="inline-flex h-12 items-center rounded-full bg-white px-6 font-bold text-ink shadow-soft ring-1 ring-bark/15 transition hover:bg-ivory">{{ __('common.logout') }}</button>
    </form>
@endsection
