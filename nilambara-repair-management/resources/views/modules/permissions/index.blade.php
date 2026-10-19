@extends('layouts.app')

@section('title', 'Permission catalog')

@section('content')
    <div class="mb-7">
        <a href="{{ route('admin.roles.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Roles & permissions</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Permission catalog</h1>
        <p class="mt-1 text-sm text-slate-500">Available application abilities grouped by module.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($permissions as $module => $items)
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ str($module)->replace('-', ' ')->headline() }}</h2>
                <ul class="mt-4 divide-y divide-slate-100">
                    @foreach ($items as $permission)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-xs">
                            <span><span class="font-semibold text-slate-700">{{ $permission->name }}</span><span class="mt-0.5 block text-[10px] text-slate-400">{{ $permission->label }}</span></span>
                            <span class="shrink-0 text-[10px] text-slate-400">{{ $permission->roles_count }} roles</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
