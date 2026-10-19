@extends('layouts.app')

@section('title', $managedUser->username)

@section('content')
    <div class="mb-7">
        <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← User accounts</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $managedUser->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $managedUser->username }} · {{ $managedUser->employee?->employee_code ?: 'No linked employee record' }}</p>
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="text-sm font-bold text-slate-900">Account details</h2>
            <form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="mt-5 space-y-4">
                @csrf @method('PUT')
                <div><label for="name" class="mb-2 block text-xs font-semibold text-slate-700">Name</label><input id="name" name="name" required value="{{ old('name', $managedUser->name) }}" class="form-input"></div>
                <div><label for="username" class="mb-2 block text-xs font-semibold text-slate-700">Username</label><input id="username" name="username" required minlength="3" maxlength="64" value="{{ old('username', $managedUser->username) }}" class="form-input"></div>
                <div><label for="email" class="mb-2 block text-xs font-semibold text-slate-700">Email (optional)</label><input id="email" name="email" type="email" value="{{ old('email', $managedUser->email) }}" class="form-input"></div>
                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">Save account</button>
            </form>
            @if (! $managedUser->is(auth()->user()))
                @can('users.disable')
                    <form method="POST" action="{{ route('admin.users.status', $managedUser) }}" class="mt-6 border-t border-slate-100 pt-5" onsubmit="return confirm('{{ $managedUser->is_active ? 'Disable this login account?' : 'Enable this login account?' }}')">
                        @csrf
                        <button class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold {{ $managedUser->is_active ? 'text-red-700 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' }}">{{ $managedUser->is_active ? 'Disable account' : 'Enable account' }}</button>
                    </form>
                @endcan
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="text-sm font-bold text-slate-900">Assigned role</h2>
            @can('roles.manage')
                @if (! $managedUser->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.role', $managedUser) }}" class="mt-5 space-y-4">
                        @csrf @method('PUT')
                        <div>
                            <label for="role_id" class="mb-2 block text-xs font-semibold text-slate-700">Role</label>
                            <select id="role_id" name="role_id" required class="form-input">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected($managedUser->roles->first()?->id === $role->id)>{{ $role->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">Update role</button>
                    </form>
                @else
                    <p class="mt-3 text-xs text-amber-700">You cannot change your own role.</p>
                @endif
            @else
                <p class="mt-3 text-sm text-slate-600">{{ $managedUser->roles->first()?->label ?: 'No role assigned' }}</p>
            @endcan
        </section>

        @can('users.update')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="text-sm font-bold text-slate-900">Administrator password reset</h2>
                <p class="mt-1 text-xs text-slate-500">The new password is never displayed. All active sessions for this account will be invalidated.</p>
                <form method="POST" action="{{ route('admin.users.password', $managedUser) }}" class="mt-5 space-y-4">
                    @csrf
                    <div><label for="password" class="mb-2 block text-xs font-semibold text-slate-700">New password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required class="form-input"></div>
                    <div><label for="password_confirmation" class="mb-2 block text-xs font-semibold text-slate-700">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required class="form-input"></div>
                    <button class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reset password</button>
                </form>
            </section>
        @endcan

        @can('permissions.manage')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 xl:col-span-2">
                <h2 class="text-sm font-bold text-slate-900">Individual permission overrides</h2>
                <p class="mt-1 text-xs text-slate-500">An allow adds direct access; a deny overrides role grants; inherit uses the assigned role only.</p>
                @if (! $managedUser->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.permissions', $managedUser) }}" class="mt-5">
                        @csrf @method('PUT')
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($permissions as $module => $items)
                                <section class="rounded-xl border border-slate-100 p-4">
                                    <h3 class="text-xs font-bold text-slate-800">{{ str($module)->replace('-', ' ')->headline() }}</h3>
                                    <div class="mt-3 space-y-3">
                                        @foreach ($items as $permission)
                                            @php($effect = $overrides->get($permission->id)?->pivot?->effect)
                                            <div class="grid grid-cols-[minmax(0,1fr)_110px] items-center gap-2">
                                                <label for="permission-{{ $permission->id }}" class="text-[11px] text-slate-600">{{ $permission->name }}</label>
                                                <select id="permission-{{ $permission->id }}" name="permissions[{{ $permission->id }}]" class="form-input !px-2 !py-1.5 text-[10px]">
                                                    <option value="inherit" @selected($effect === null)>Inherit</option>
                                                    <option value="allow" @selected($effect === 'allow')>Allow</option>
                                                    <option value="deny" @selected($effect === 'deny')>Deny</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                        <button class="mt-5 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">Save overrides</button>
                    </form>
                @else
                    <p class="mt-3 text-xs text-amber-700">You cannot change your own permission overrides.</p>
                @endif
            </section>
        @endcan
    </div>
@endsection
