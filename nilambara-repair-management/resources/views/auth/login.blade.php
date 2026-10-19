@extends('layouts.auth')

@section('title', __('ui.sign_in'))

@section('content')
    <div class="mb-8">
        <span class="mb-6 inline-flex size-11 items-center justify-center rounded-xl border border-teal-100 bg-teal-50 text-teal-700"><x-icon name="lock" :size="21" /></span>
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.employee_portal') }}</p>
        <h1 class="text-[30px] font-bold tracking-tight text-slate-900">{{ __('ui.welcome_back') }}</h1>
        <p class="mt-2 text-[13px] leading-6 text-slate-500">{{ __('ui.sign_in_description') }}</p>
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-xs leading-5 text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label for="username" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.username') }}</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="{{ __('ui.username_placeholder') }}" @class(['form-input', 'has-error' => $errors->has('username')]) @if($errors->has('username')) aria-invalid="true" aria-describedby="username-error" @endif>
            @error('username')<p id="username-error" role="alert" class="mt-2 text-xs leading-5 text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-data="{ showPassword: false }">
            <label for="password" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.password') }}</label>
            <div class="relative">
                <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="{{ __('ui.password_placeholder') }}" @class(['form-input pr-12', 'has-error' => $errors->has('password')]) @if($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
                <button x-cloak type="button" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 hover:text-teal-700" x-on:click="showPassword = !showPassword" :aria-pressed="showPassword" :aria-label="showPassword ? @js(__('ui.hide_password')) : @js(__('ui.show_password'))"><span x-show="!showPassword"><x-icon name="eye" :size="18" /></span><span x-show="showPassword"><x-icon name="eye-off" :size="18" /></span></button>
            </div>
            @error('password')<p id="password-error" role="alert" class="mt-2 text-xs leading-5 text-red-600">{{ $message }}</p>@enderror
        </div>
        <label for="remember" class="flex w-fit cursor-pointer items-center gap-2.5 py-1 text-xs text-slate-500"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="size-4 rounded border-slate-300 accent-teal-700">{{ __('ui.remember_me') }}</label>
        <button type="submit" class="sign-in-button flex w-full items-center justify-center gap-3 rounded-xl bg-teal-700 px-5 py-3.5 text-xs font-semibold text-white transition hover:bg-teal-800">{{ __('ui.sign_in') }}<x-icon name="arrow-right" :size="16" /></button>
    </form>

    <div class="mt-8 flex items-start gap-3 rounded-xl border border-slate-200/80 bg-slate-50 p-4">
        <x-icon name="shield" :size="17" class="mt-0.5 shrink-0 text-slate-400" />
        <div><p class="text-[11px] font-semibold text-slate-600">{{ __('ui.admin_managed_accounts') }}</p><p class="mt-1 text-[11px] leading-5 text-slate-400">{{ __('ui.admin_managed_description') }}</p></div>
    </div>
@endsection
