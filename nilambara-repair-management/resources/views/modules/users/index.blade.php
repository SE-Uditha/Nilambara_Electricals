@extends('layouts.app')

@section('title', 'User accounts')

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">Administration</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">User accounts</h1>
        <p class="mt-1 text-sm text-slate-500">Manage enabled accounts, assigned roles, and staff links.</p>
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-5 py-3">Account</th><th class="px-5 py-3">Employee</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Last login</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-4"><span class="font-semibold text-slate-900">{{ $user->name }}</span><span class="mt-1 block text-slate-400">{{ $user->username }}</span></td>
                            <td class="px-5 py-4">{{ $user->employee?->employee_code ?: '—' }}</td>
                            <td class="px-5 py-4">{{ $user->roles->first()?->label ?: 'No role' }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $user->last_login_at?->timezone(config('business.timezone'))->format('d M Y, h:i A') ?: 'Never' }}</td>
                            <td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-[10px] font-semibold', 'bg-emerald-50 text-emerald-700' => $user->is_active, 'bg-slate-100 text-slate-500' => ! $user->is_active])>{{ $user->is_active ? 'Enabled' : 'Disabled' }}</span></td>
                            <td class="px-5 py-4"><a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-teal-700 hover:text-teal-900">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">No user accounts have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>@endif
    </div>
@endsection
