<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="app-body" x-data="{ sidebarOpen: false, desktop: window.innerWidth >= 1024 }" x-on:resize.window="desktop = window.innerWidth >= 1024; if (desktop) sidebarOpen = false" x-on:keydown.escape.window="if (sidebarOpen) { sidebarOpen = false; $refs.menuButton.focus() }" :class="{ 'drawer-open': sidebarOpen }">
    <a href="#main-content" class="skip-link">{{ __('ui.skip_to_content') }}</a>
    <button x-cloak x-show="sidebarOpen" x-transition.opacity type="button" class="sidebar-backdrop lg:hidden" x-on:click="sidebarOpen = false; $refs.menuButton.focus()" aria-label="{{ __('ui.close_navigation') }}"></button>

    <aside id="main-sidebar" class="app-sidebar" :class="{ 'is-open': sidebarOpen }" :inert="!desktop && !sidebarOpen" aria-label="{{ __('ui.main_navigation') }}">
        <div class="flex h-[88px] shrink-0 items-center justify-between border-b border-white/8 px-5">
            <a href="{{ route('dashboard') }}" class="rounded-lg text-white" aria-label="{{ config('app.name') }} — {{ __('ui.dashboard') }}"><x-app-logo /></a>
            <button type="button" x-ref="closeNavigation" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 lg:hidden" x-on:click="sidebarOpen = false; $refs.menuButton.focus()" aria-label="{{ __('ui.close_navigation') }}"><x-icon name="close" :size="18" /></button>
        </div>
        <nav class="sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 pt-5 pb-6">
            @foreach (config('navigation') as $group)
                <div class="mb-5 last:mb-0">
                    <h2 class="mb-2 px-3 text-[9px] font-semibold tracking-[0.15em] text-slate-500 uppercase">{{ __('ui.'.$group['label']) }}</h2>
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @if ((! isset($item['roles']) || auth()->user()->hasAnyRole($item['roles']))
                                && (! isset($item['except_roles']) || ! auth()->user()->hasAnyRole($item['except_roles']))
                                && (! isset($item['permission']) || auth()->user()->can($item['permission'])))
                                <x-nav-item :item="$item" />
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        @php($user = auth()->user())
        <div class="shrink-0 border-t border-white/8 bg-black/10 p-4">
            <div class="flex items-center gap-3 px-1">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-sm font-semibold text-teal-200" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
                    <p class="mt-0.5 truncate text-[10px] text-slate-400">{{ $user->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white" aria-label="{{ __('ui.sign_out') }}" title="{{ __('ui.sign_out') }}"><x-icon name="logout" :size="18" /></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="app-workspace">
        <header class="app-topbar">
            <div class="flex min-w-0 items-center gap-4">
                <button type="button" x-ref="menuButton" class="-ml-2 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" x-on:click="sidebarOpen = true; $nextTick(() => $refs.closeNavigation.focus())" aria-controls="main-sidebar" :aria-expanded="sidebarOpen" aria-label="{{ __('ui.open_navigation') }}"><x-icon name="menu" /></button>
                <div class="flex min-w-0 items-center gap-2.5 text-xs">
                    <span class="hidden text-slate-400 sm:inline">{{ __('ui.workspace') }}</span>
                    <span class="hidden text-slate-300 sm:inline" aria-hidden="true">/</span>
                    <span class="font-semibold text-slate-700">@yield('title', __('ui.dashboard'))</span>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-4">
                <div class="hidden items-center gap-2 text-[11px] text-slate-500 md:flex">
                    <x-icon name="calendar" :size="15" />
                    <time datetime="{{ now(config('business.timezone', 'Asia/Colombo'))->toDateString() }}">{{ now(config('business.timezone', 'Asia/Colombo'))->translatedFormat('D, '.config('business.date_format', 'd M Y')) }}</time>
                    <span class="ml-1 border-l border-slate-200 pl-2 text-[10px] text-slate-400">{{ config('business.timezone', 'Asia/Colombo') }}</span>
                </div>
                <div class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[10px] font-semibold text-slate-600" title="{{ __('ui.language') }}"><x-icon name="globe" :size="14" /><span>{{ strtoupper(app()->getLocale()) }}</span></div>
                <span class="hidden size-8 items-center justify-center rounded-full border-2 border-white bg-teal-50 text-[11px] font-bold text-teal-700 ring-1 ring-slate-200 sm:flex" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            </div>
        </header>

        <main id="main-content" class="mx-auto w-full max-w-[1600px] flex-1 px-5 py-7 sm:px-8 lg:px-9 lg:py-9" tabindex="-1">
            @yield('content')
        </main>
        <footer class="mx-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/80 py-5 text-[10px] text-slate-400 sm:mx-8 lg:mx-9">
            <p>© {{ now(config('business.timezone', 'Asia/Colombo'))->year }} {{ config('business.name', 'Nilambara Electricals') }}</p>
            <p>{{ config('business.currency', 'LKR') }} <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span> {{ config('business.timezone', 'Asia/Colombo') }}</p>
        </footer>
    </div>
    @livewireScripts
</body>
</html>
