@extends('layouts.app')

@section('title', 'Employee permissions')

@section('content')
    <div class="mb-7">
        <a href="{{ route('employees.show', $employee) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Employee details</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Individual permission overrides</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $employee->name }} · {{ $employee->user?->username }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <p class="text-sm text-slate-600">Manage individual grants and denials from the user account page.</p>
        <a href="{{ route('admin.users.show', $employee->user) }}" class="mt-4 inline-flex rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">Manage user permissions</a>
        <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($permissions as $module => $items)
                <section class="rounded-xl border border-slate-100 p-4">
                    <h2 class="text-xs font-bold text-slate-800">{{ str($module)->replace('-', ' ')->headline() }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($items as $permission)
                            @php($override = $overrides->get($permission->id))
                            <li class="flex justify-between gap-3 text-[11px]">
                                <span class="text-slate-600">{{ $permission->name }}</span>
                                <span class="font-semibold {{ $override?->pivot->effect === 'deny' ? 'text-red-600' : ($override ? 'text-emerald-700' : 'text-slate-400') }}">{{ $override?->pivot->effect ? ucfirst($override->pivot->effect) : 'Inherited' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
@endsection
