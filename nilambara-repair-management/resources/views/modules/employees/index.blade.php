@extends('layouts.app')

@section('title', __('ui.employees'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">Administration</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Employees</h1>
            <p class="mt-1 text-sm text-slate-500">Employee records and linked staff accounts.</p>
        </div>
        @can('employees.create')
            <a href="{{ route('employees.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-teal-800">Register employee</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_200px_160px_auto]">
        <label class="sr-only" for="employee-search">Search employees</label>
        <input id="employee-search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Search ID, name, or mobile">
        <select name="role" class="form-input">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->label }}</option>
            @endforeach
        </select>
        <select name="status" class="form-input">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">Employee ID</th>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Mobile</th>
                        <th class="px-5 py-3">Job / Department</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Joined</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        <tr class="text-slate-700">
                            <td class="px-5 py-4 font-mono text-[11px] text-slate-500">{{ $employee->employee_code }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ route('employees.show', $employee) }}" class="font-semibold text-slate-900 hover:text-teal-700">{{ $employee->name }}</a>
                                @if ($employee->user)
                                    <span class="mt-1 block text-[10px] text-slate-400">{{ $employee->user->username }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">{{ $employee->mobile_number ?: '—' }}</td>
                            <td class="px-5 py-4">{{ $employee->job_title ?: '—' }}<span class="mt-1 block text-[10px] text-slate-400">{{ $employee->department ?: '—' }}</span></td>
                            <td class="px-5 py-4">{{ $employee->user?->roles->first()?->label ?: '—' }}</td>
                            <td class="px-5 py-4">{{ $employee->date_joined?->format(config('business.date_format', 'd M Y')) ?: '—' }}</td>
                            <td class="px-5 py-4">
                                <span @class(['rounded-full px-2.5 py-1 text-[10px] font-semibold', 'bg-emerald-50 text-emerald-700' => $employee->is_active, 'bg-slate-100 text-slate-500' => ! $employee->is_active])>{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                                @if ($employee->user && ! $employee->user->is_active)
                                    <span class="mt-1 block text-[10px] text-amber-700">Login disabled</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex gap-3">
                                    <a href="{{ route('employees.show', $employee) }}" class="font-semibold text-teal-700 hover:text-teal-900">View</a>
                                    @can('employees.update')
                                        <a href="{{ route('employees.edit', $employee) }}" class="font-semibold text-slate-600 hover:text-slate-900">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">No employees found</p><p class="mt-1 text-xs text-slate-400">Change the search or filter criteria, or register an employee.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $employees->links() }}</div>
        @endif
    </div>
@endsection
