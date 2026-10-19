<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="auth-body">
    <a href="#main-content" class="skip-link">{{ __('ui.skip_to_content') }}</a>
    <div class="auth-shell">
        <aside class="auth-brand-panel" aria-label="{{ config('business.name', 'Nilambara Electricals') }}">
            <div class="relative z-1"><x-app-logo class="text-white" /></div>
            <div class="relative z-1 my-auto py-16">
                <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-[10px] font-medium text-teal-200"><span class="size-1.5 rounded-full bg-teal-300" aria-hidden="true"></span>{{ __('ui.built_for_workshop') }}</div>
                <h2 class="max-w-sm text-[42px] leading-[1.15] font-semibold tracking-[-0.035em] text-white xl:text-[52px]">{{ __('ui.every_repair') }}<br><span class="text-teal-300">{{ __('ui.one_workspace') }}</span></h2>
                <p class="mt-6 max-w-[330px] text-sm leading-7 text-slate-300">{{ __('ui.login_brand_description') }}</p>
                <div class="mt-10 h-px w-12 bg-teal-400/60"></div>
                <p class="mt-6 text-[10px] font-medium tracking-[0.13em] text-slate-400 uppercase">{{ __('ui.electrical_motor_repairs') }}</p>
            </div>
            <div class="relative z-1 flex items-center justify-between gap-4 border-t border-white/10 pt-6 text-[10px] text-slate-400"><span>{{ __('ui.staff_access_only') }}</span><span class="flex items-center gap-1.5"><x-icon name="lock" :size="12" />{{ __('ui.secure_workspace') }}</span></div>
            <svg class="auth-motor-art" viewBox="0 0 600 600" fill="none" aria-hidden="true"><circle cx="420" cy="400" r="240" stroke="currentColor" /><circle cx="420" cy="400" r="190" stroke="currentColor" /><circle cx="420" cy="400" r="140" stroke="currentColor" /><circle cx="420" cy="400" r="90" stroke="currentColor" /><path d="M420 160v70m240 170h-70M420 640v-70M180 400h70M250 230l50 50M590 230l-50 50M590 570l-50-50M250 570l50-50" stroke="currentColor" /><path d="m430 285-70 135h65l-15 110 95-160h-70l-5-85Z" fill="currentColor" opacity=".6" /></svg>
        </aside>
        <div class="auth-form-panel">
            <div class="mb-12 flex items-center justify-between lg:hidden"><x-app-logo class="text-slate-900" /><span class="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-400">{{ strtoupper(app()->getLocale()) }}</span></div>
            <main id="main-content" class="auth-form-main" tabindex="-1">@yield('content')</main>
            <footer class="mt-14 flex flex-wrap items-center justify-between gap-3 text-[10px] text-slate-400"><p>© {{ now(config('business.timezone', 'Asia/Colombo'))->year }} {{ config('business.name', 'Nilambara Electricals') }}</p><span>{{ config('business.timezone', 'Asia/Colombo') }}</span></footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>
