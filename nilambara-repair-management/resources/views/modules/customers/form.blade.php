<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    @php($type = old('customer_type', $customer?->customer_type?->value ?? 'individual'))
    <section x-data="{ type: @js($type) }" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <h2 class="text-sm font-bold text-slate-900">{{ __('ui.customer_information') }}</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @if ($customer)
                <div class="sm:col-span-2 rounded-xl bg-slate-50 p-4">
                    <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.customer_id') }}</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-slate-700">{{ $customer->customer_code }}</p>
                </div>
            @endif
            <div>
                <label for="customer_type" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.customer_type') }} <span class="text-red-600">*</span></label>
                <select id="customer_type" name="customer_type" x-model="type" required class="form-input @error('customer_type') has-error @enderror">
                    <option value="individual">{{ __('ui.individual') }}</option>
                    <option value="company">{{ __('ui.company') }}</option>
                </select>
                @error('customer_type')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="type === 'individual'">
                <label for="customer_name" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.customer_name') }} <span class="text-red-600">*</span></label>
                <input id="customer_name" name="customer_name" value="{{ old('customer_name', $customer?->customer_name) }}" maxlength="255" x-bind:required="type === 'individual'" x-bind:disabled="type !== 'individual'" class="form-input @error('customer_name') has-error @enderror">
                @error('customer_name')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="type === 'company'">
                <label for="company_name" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.company_name') }} <span class="text-red-600">*</span></label>
                <input id="company_name" name="company_name" value="{{ old('company_name', $customer?->company_name) }}" maxlength="255" x-bind:required="type === 'company'" x-bind:disabled="type !== 'company'" class="form-input @error('company_name') has-error @enderror">
                @error('company_name')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-show="type === 'company'">
                <label for="contact_person" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.contact_person') }}</label>
                <input id="contact_person" name="contact_person" value="{{ old('contact_person', $customer?->contact_person) }}" maxlength="255" class="form-input @error('contact_person') has-error @enderror">
                @error('contact_person')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mobile_number" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.mobile_number') }} <span class="text-red-600">*</span></label>
                <input id="mobile_number" name="mobile_number" type="tel" value="{{ old('mobile_number', $customer?->mobile_number) }}" maxlength="30" required autocomplete="tel" placeholder="+94 77 123 4567" class="form-input @error('mobile_number') has-error @enderror">
                @error('mobile_number')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="alternate_phone" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.alternative_telephone') }}</label>
                <input id="alternate_phone" name="alternate_phone" type="tel" value="{{ old('alternate_phone', $customer?->alternate_phone) }}" maxlength="30" class="form-input @error('alternate_phone') has-error @enderror">
                @error('alternate_phone')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.email_address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $customer?->email) }}" maxlength="255" autocomplete="email" class="form-input @error('email') has-error @enderror">
                @error('email')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.address') }} <span class="text-red-600">*</span></label>
                <textarea id="address" name="address" rows="3" maxlength="5000" required class="form-input @error('address') has-error @enderror">{{ old('address', $customer?->address) }}</textarea>
                @error('address')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            @if (! $customer)
                @can('customers.statement')
                    <div>
                        <label for="opening_balance" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.opening_balance') }}</label>
                        <input id="opening_balance" name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance', '0.00') }}" class="form-input @error('opening_balance') has-error @enderror">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('ui.opening_balance_help') }}</p>
                        @error('opening_balance')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endcan
                <div>
                    <label for="registration_date" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.registration_date') }} <span class="text-red-600">*</span></label>
                    <input id="registration_date" name="registration_date" type="date" value="{{ old('registration_date', now()->toDateString()) }}" required class="form-input @error('registration_date') has-error @enderror">
                    @error('registration_date')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @can('customers.deactivate')
                    <div>
                        <label for="is_active" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.status') }}</label>
                        <select id="is_active" name="is_active" class="form-input">
                            <option value="1" @selected((string) old('is_active', '1') === '1')>{{ __('ui.active') }}</option>
                            <option value="0" @selected((string) old('is_active') === '0')>{{ __('ui.inactive') }}</option>
                        </select>
                    </div>
                @endcan
            @else
                @can('customers.statement', $customer)
                    <div class="sm:col-span-2 rounded-xl border border-amber-100 bg-amber-50 p-4">
                        <p class="text-xs font-semibold text-amber-900">{{ __('ui.opening_balance') }}: {{ config('business.currency') }} {{ number_format((float) $customer->opening_balance, 2) }}</p>
                        <p class="mt-1 text-xs text-amber-800">{{ __('ui.opening_balance_immutable') }}</p>
                    </div>
                @endcan
            @endif
            <div class="sm:col-span-2">
                <label for="special_notes" class="mb-2 block text-xs font-semibold text-slate-700">{{ __('ui.special_notes') }}</label>
                <textarea id="special_notes" name="special_notes" rows="3" maxlength="10000" class="form-input @error('special_notes') has-error @enderror">{{ old('special_notes', $customer?->special_notes) }}</textarea>
                @error('special_notes')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('ui.review_customer_fields') }}</div>
    @endif
    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.cancel') }}</a>
        <button type="submit" class="rounded-xl bg-teal-700 px-5 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ $customer ? __('ui.save_changes') : __('ui.register_customer') }}</button>
    </div>
</form>
