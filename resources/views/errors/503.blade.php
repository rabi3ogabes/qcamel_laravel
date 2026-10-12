@extends('errors.layout')

@section('code', '503')
@section('title', __('common.maintenance_title'))
@section('actions')
    <a href="{{ url()->current() }}" class="inline-flex h-12 items-center rounded-full bg-white px-6 font-bold text-ink shadow-soft ring-1 ring-bark/15 transition hover:bg-ivory">{{ __('common.refresh') }}</a>
@endsection
