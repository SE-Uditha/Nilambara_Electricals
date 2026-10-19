@extends('layouts.app')

@section('title', 'Edit role')

@section('content')
    <div class="mb-7">
        <a href="{{ route('admin.roles.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Roles & permissions</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Manage {{ $role->label }}</h1>
        <p class="mt-1 text-sm text-slate-500">Adjust this role's access. User-specific allows and denials remain independent.</p>
    </div>
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($role->name === 'administrator')
        <div class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">Administrator always receives every available permission.</div>
    @else
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf @method('PUT')
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($permissions as $module => $items)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h2 class="text-sm font-bold text-slate-900">{{ str($module)->replace('-', ' ')->headline() }}</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($items as $permission)
                                <label class="flex items-start gap-2.5 text-xs text-slate-600">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) class="mt-0.5 size-4 rounded border-slate-300 accent-teal-700">
                                    <span><span class="font-semibold text-slate-700">{{ $permission->name }}</span><span class="mt-0.5 block text-[10px] text-slate-400">{{ $permission->label }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">Cancel</a>
                <button class="rounded-xl bg-teal-700 px-5 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">Save permissions</button>
            </div>
        </form>
    @endif
@endsection
