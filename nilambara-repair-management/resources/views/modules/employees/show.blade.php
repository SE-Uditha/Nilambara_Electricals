@extends('layouts.app')

@section('title', $employee->name)

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('employees.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Employees</a>
            <p class="mt-4 font-mono text-[11px] text-slate-400">{{ $employee->employee_code }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $employee->name }}</h1>
        </div>
        @can('employees.update')
            <a href="{{ route('employees.edit', $employee) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit employee</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(300px,0.8fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="text-sm font-bold text-slate-900">Employee information</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach (['Mobile' => $employee->mobile_number, 'Job title' => $employee->job_title, 'Department' => $employee->department, 'Date joined' => $employee->date_joined?->format(config('business.date_format', 'd M Y')), 'Address' => $employee->address] as $label => $value)
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ $label }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $value ?: '—' }}</dd></div>
                @endforeach
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Employment status</dt><dd class="mt-1 text-sm font-semibold {{ $employee->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $employee->is_active ? 'Active' : 'Inactive' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900">Login account</h2>
                @if ($employee->user)
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $employee->user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $employee->user->is_active ? 'Enabled' : 'Disabled' }}</span>
                @endif
            </div>
            @if ($employee->user)
                <dl class="mt-5 space-y-4">
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Username</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $employee->user->username }}</dd></div>
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Role</dt><dd class="mt-1 text-sm text-slate-700">{{ $employee->user->roles->first()?->label ?: 'No role assigned' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Last login</dt><dd class="mt-1 text-sm text-slate-700">{{ $employee->user->last_login_at?->timezone(config('business.timezone'))->format('d M Y, h:i A') ?: 'Never' }}</dd></div>
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Account created</dt><dd class="mt-1 text-sm text-slate-700">{{ $employee->user->created_at?->format('d M Y') ?: '—' }}</dd></div>
                </dl>
                @can('users.view')
                    <a href="{{ route('admin.users.show', $employee->user) }}" class="mt-6 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-900">Manage account →</a>
                @endcan
                @can('permissions.manage')
                    <a href="{{ route('employees.permissions', $employee) }}" class="mt-3 block text-xs font-semibold text-teal-700 hover:text-teal-900">View individual permission overrides →</a>
                @endcan
            @else
                <p class="mt-4 text-sm text-slate-500">No login account is linked to this employee.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 xl:col-span-2">
            <h2 class="text-sm font-bold text-slate-900">Recent administrative actions</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($recentActions as $action)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-xs">
                        <span class="font-semibold text-slate-700">{{ str($action->action)->replace('.', ' ')->headline() }}</span>
                        <time class="text-slate-400">{{ $action->occurred_at?->timezone(config('business.timezone'))->format('d M Y, h:i A') }}</time>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-slate-400">No administrative actions recorded.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
