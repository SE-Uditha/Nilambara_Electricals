@extends('layouts.auth')

@section('title', 'Access denied')

@section('content')
    <div class="max-w-md">
        <span class="mb-6 inline-flex size-11 items-center justify-center rounded-xl border border-amber-100 bg-amber-50 text-amber-700"><x-icon name="lock" :size="21" /></span>
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-amber-700 uppercase">403 · Access denied</p>
        <h1 class="text-[30px] font-bold tracking-tight text-slate-900">You don't have access to this page.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Your account does not have the permission required for this action. Contact an administrator if you believe this is a mistake.</p>
        @auth
            <a href="{{ route('dashboard') }}" class="mt-7 inline-flex rounded-xl bg-teal-700 px-5 py-3 text-xs font-semibold text-white hover:bg-teal-800">Return to dashboard</a>
        @else
            <a href="{{ route('login') }}" class="mt-7 inline-flex rounded-xl bg-teal-700 px-5 py-3 text-xs font-semibold text-white hover:bg-teal-800">Sign in</a>
        @endauth
    </div>
@endsection
