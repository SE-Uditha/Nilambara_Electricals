@extends('layouts.app')

@section('title', 'Roles and permissions')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">Administration</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Roles & permissions</h1>
            <p class="mt-1 text-sm text-slate-500">Default business roles and their module access.</p>
        </div>
        @can('permissions.manage')
            <a href="{{ route('admin.permissions.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Permission catalog</a>
        @endcan
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="font-bold text-slate-900">{{ $role->label }}</h2><p class="mt-1 text-xs text-slate-500">{{ $role->description }}</p></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-semibold text-slate-500">{{ $role->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-[10px] text-slate-500">
                    <span>{{ $role->users_count }} users · {{ $role->permissions_count }} permissions</span>
                    @can('roles.manage')
                        <a href="{{ route('admin.roles.edit', $role) }}" class="font-semibold text-teal-700 hover:text-teal-900">Manage access →</a>
                    @endcan
                </div>
            </article>
        @endforeach
    </div>
@endsection
