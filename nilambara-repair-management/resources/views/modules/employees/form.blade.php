<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <h2 class="text-sm font-bold text-slate-900">Employee information</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="mb-2 block text-xs font-semibold text-slate-700">Employee name <span class="text-red-600">*</span></label>
                <input id="name" name="name" value="{{ old('name', $employee->name) }}" required maxlength="255" class="form-input @error('name') has-error @enderror">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mobile_number" class="mb-2 block text-xs font-semibold text-slate-700">Mobile number</label>
                <input id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $employee->mobile_number) }}" maxlength="30" class="form-input" placeholder="077 123 4567">
                @error('mobile_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="date_joined" class="mb-2 block text-xs font-semibold text-slate-700">Date joined</label>
                <input id="date_joined" name="date_joined" type="date" value="{{ old('date_joined', $employee->date_joined?->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" class="form-input">
                @error('date_joined')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="job_title" class="mb-2 block text-xs font-semibold text-slate-700">Job title</label>
                <input id="job_title" name="job_title" value="{{ old('job_title', $employee->job_title) }}" maxlength="100" class="form-input">
            </div>
            <div>
                <label for="department" class="mb-2 block text-xs font-semibold text-slate-700">Department</label>
                <input id="department" name="department" value="{{ old('department', $employee->department) }}" maxlength="100" class="form-input">
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="mb-2 block text-xs font-semibold text-slate-700">Address</label>
                <textarea id="address" name="address" rows="3" maxlength="5000" class="form-input">{{ old('address', $employee->address) }}</textarea>
            </div>
            <div>
                <label for="is_active" class="mb-2 block text-xs font-semibold text-slate-700">Employment status</label>
                <select id="is_active" name="is_active" class="form-input">
                    <option value="1" @selected((string) old('is_active', (int) $employee->is_active) === '1')>Active</option>
                    <option value="0" @selected((string) old('is_active', (int) $employee->is_active) === '0')>Inactive</option>
                </select>
                @error('is_active')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    @if ($method === 'POST' && $canCreateAccount)
        <section x-data="{ createAccount: {{ old('create_account') ? 'true' : 'false' }} }" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="flex items-start gap-3">
                <input id="create_account" name="create_account" type="checkbox" value="1" x-model="createAccount" class="mt-1 size-4 rounded border-slate-300 accent-teal-700">
                <div><label for="create_account" class="text-sm font-bold text-slate-900">Create a linked login account</label><p class="mt-1 text-xs text-slate-500">Only authorized administrators can create staff accounts and assign roles.</p></div>
            </div>
            <div x-cloak x-show="createAccount" class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <div>
                    <label for="username" class="mb-2 block text-xs font-semibold text-slate-700">Username <span class="text-red-600">*</span></label>
                    <input id="username" name="username" value="{{ old('username') }}" maxlength="64" autocomplete="off" class="form-input">
                    @error('username')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="mb-2 block text-xs font-semibold text-slate-700">Email (optional)</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" class="form-input">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="role_id" class="mb-2 block text-xs font-semibold text-slate-700">Role <span class="text-red-600">*</span></label>
                    <select id="role_id" name="role_id" class="form-input">
                        <option value="">Choose a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="mb-2 block text-xs font-semibold text-slate-700">Temporary password <span class="text-red-600">*</span></label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" class="form-input">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-2 block text-xs font-semibold text-slate-700">Confirm password <span class="text-red-600">*</span></label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" class="form-input">
                </div>
            </div>
        </section>
    @elseif ($employee->user)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="text-sm font-bold text-slate-900">Linked account</h2>
            <p class="mt-2 text-sm text-slate-600">Username: <span class="font-semibold">{{ $employee->user->username }}</span></p>
            <p class="mt-1 text-xs text-slate-500">Change roles, status, or passwords from user account management.</p>
        </section>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Please review the highlighted fields.</div>
    @endif

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ $method === 'POST' ? route('employees.index') : route('employees.show', $employee) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-xl bg-teal-700 px-5 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ $method === 'POST' ? 'Register employee' : 'Save changes' }}</button>
    </div>
</form>
