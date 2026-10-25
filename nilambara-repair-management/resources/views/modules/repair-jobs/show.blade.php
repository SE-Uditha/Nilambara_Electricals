@extends('layouts.app')

@section('title', __('ui.repair_job').' · '.$job->automatic_number)

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $job->automatic_number }} <span class="font-normal text-slate-400">/ {{ $job->job_number }}</span></h1>
            <p class="mt-1 text-sm text-slate-500">{{ $job->customer->display_name }} · {{ $job->item_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('repair-jobs.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.back_to_jobs') }}</a>
            @can('update', $job)<a href="{{ route('repair-jobs.edit', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.edit_job') }}</a>@endcan
                        @if (\App\Modules\Approvals\Services\ApprovalAccess::canRequestChange(auth()->user(), 'job_update'))<a href="{{ route('repair-jobs.request-edit', $job) }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-900 hover:bg-amber-100">{{ __('ui.request_edit') }}</a>@endif
                        @can('assign', $job)<a href="{{ route('repair-jobs.assign-form', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.assign_technician') }}</a>@endcan
            @can('delete', $job)
                <form method="POST" action="{{ route('repair-jobs.destroy', $job) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_job') }}')">@csrf @method('DELETE')<button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-100">{{ __('ui.delete_job') }}</button></form>
            @elseif (\App\Modules\Approvals\Services\ApprovalAccess::canRequestChange(auth()->user(), 'job_delete'))
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-100">{{ __('ui.request_delete') }}</summary>
                    <form method="POST" action="{{ route('repair-jobs.request-delete', $job) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-4 shadow-lg">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <label class="form-label" for="delete_reason">{{ __('ui.reason_for_deletion') }} <span class="text-red-600">*</span></label>
                        <textarea id="delete_reason" name="reason" rows="3" minlength="5" maxlength="2000" required class="form-input"></textarea>
                        <button class="w-full rounded-xl bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700">{{ __('ui.submit_delete_request') }}</button>
                    </form>
                </details>
            @endcan
            @if (in_array($job->status, \App\Enums\JobStatus::billable(), true) && ! $job->invoiceJobs()->exists())
                @can('invoices.create')<a href="{{ route('repair-jobs.billing', $job) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">Prepare Final Bill / Invoice</a>@endcan
            @endif
            @if ($job->receipt)
                @can('receipts.print')
                    <a href="{{ route('repair-jobs.receipt.print', $job) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.print_receipt') }}</a>
                @endcan
                @can('receipts.reprint')<a href="{{ route('repair-jobs.receipt.duplicate', $job) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('ui.reprint_duplicate') }}</a>@endcan
            @endif
            @can('job-cards.print')<a href="{{ route('job-cards.print', $job) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.print_job_card') }}</a>@endcan
        </div>
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">{{ __('ui.job_information') }}</h2>
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">{{ $job->status->value }}</span>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt class="form-label">{{ __('ui.job_reference') }}</dt><dd class="font-mono font-semibold text-teal-800">{{ $job->automatic_number }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.manual_job_number') }}</dt><dd class="font-mono text-slate-800">{{ $job->job_number }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.job_type') }}</dt><dd class="text-slate-700">{{ __('ui.job_type_'.$job->job_type) }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.customer') }}</dt><dd class="text-slate-700">{{ $job->customer->display_name }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.received_date') }}</dt><dd class="text-slate-700">{{ $job->received_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.received_by') }}</dt><dd class="text-slate-700">{{ $job->receivedByEmployee->name }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.item_name') }}</dt><dd class="text-slate-700">{{ $job->item_name }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.brand_model') }}</dt><dd class="text-slate-700">{{ trim(($job->brand ?? '').' '.($job->model ?? '')) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.technician') }}</dt><dd class="text-slate-700">{{ $job->technician?->name ?: __('ui.not_assigned') }}</dd></div>
                    <div class="sm:col-span-2 xl:col-span-3"><dt class="form-label">{{ __('ui.item_description') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->item_description ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 xl:col-span-3"><dt class="form-label">{{ __('ui.reported_fault') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->reported_fault ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.accessories_received') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->accessories_received ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.physical_condition') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->physical_condition ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.visible_damage') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->visible_damage ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 xl:col-span-3"><dt class="form-label">{{ __('ui.special_notes') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ $job->special_notes ?: '—' }}</dd></div>
                </dl>
                @if ($job->receipt && auth()->user()->can('receipts.print'))
                    <div class="mt-5 border-t border-slate-100 pt-4"><a href="{{ route('repair-jobs.receipt.print', $job) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.view_receipt') }} · {{ $job->receipt->receipt_number }}</a></div>
                @endif
            </section>

            @php($contactFailed = in_array($job->status, [\App\Enums\JobStatus::CustomerContactFailed, \App\Enums\JobStatus::CustomerContactFailedAfterReady], true))
            @if ($contactFailed || $job->contactAttempts->isNotEmpty())
                <section id="contact-attempts" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-slate-900">{{ __('ui.contact_attempts') }}</h2>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">{{ __('ui.times_contacted', ['count' => $job->contactAttempts->count()]) }}</span>
                    </div>
                    <ol class="mb-5 space-y-2">
                        @forelse ($job->contactAttempts as $attempt)
                            <li class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                                <span class="font-semibold">#{{ $attempt->attempt_number }}</span> · {{ $attempt->contacted_on->format(config('business.date_format')) }}
                                @if ($attempt->recordedBy) · {{ $attempt->recordedBy->name }} @endif
                                @if ($attempt->notes)<p class="mt-1 whitespace-pre-line text-slate-600">{{ $attempt->notes }}</p>@endif
                            </li>
                        @empty
                            <li class="rounded-xl bg-slate-50 px-4 py-4 text-sm text-slate-500">{{ __('ui.no_contact_attempts') }}</li>
                        @endforelse
                    </ol>
                    @if ($contactFailed)
                        @can('jobs.update-status')
                            <form method="POST" action="{{ route('repair-jobs.contact-attempts.store', $job) }}" class="grid gap-3 sm:grid-cols-[200px_1fr_auto] sm:items-end">
                                @csrf
                                <div>
                                    <label class="form-label" for="contacted_on">{{ __('ui.date_contacted') }} <span class="text-red-600">*</span></label>
                                    <input id="contacted_on" name="contacted_on" type="date" required max="{{ now()->timezone(config('business.timezone'))->toDateString() }}" value="{{ old('contacted_on', now()->timezone(config('business.timezone'))->toDateString()) }}" class="form-input">
                                    @error('contacted_on')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="form-label" for="contact_notes">{{ __('ui.contact_notes') }}</label>
                                    <input id="contact_notes" name="notes" type="text" maxlength="1000" value="{{ old('notes') }}" class="form-input">
                                    @error('notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.add_contact_attempt') }}</button>
                            </form>
                        @endcan
                    @endif
                </section>
            @endif

            @if ($job->status !== \App\Enums\JobStatus::Cancelled)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.inspection_and_repair_history') }}</h2>
                <div class="space-y-4">
                    @forelse ($job->jobInspections->sortByDesc('inspected_at') as $inspection)
                        <article class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-wrap justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-800">{{ __('ui.inspected_by') }}: {{ $inspection->inspectedByEmployee?->name ?: '—' }}</p>
                                <time class="text-[10px] text-slate-500">{{ $inspection->inspected_at?->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</time>
                            </div>
                            @if ($inspection->inspection_result)<p class="mt-3 whitespace-pre-line text-xs text-slate-700"><strong>{{ __('ui.inspection_result') }}:</strong> {{ $inspection->inspection_result }}</p>@endif
                            @if ($inspection->technical_information)<p class="mt-2 whitespace-pre-line text-xs text-slate-700"><strong>{{ __('ui.technical_information') }}:</strong> {{ $inspection->technical_information }}</p>@endif
                            @if ($inspection->work_performed)<p class="mt-2 whitespace-pre-line text-xs text-slate-700"><strong>{{ __('ui.work_performed') }}:</strong> {{ $inspection->work_performed }}</p>@endif
                            @if ($inspection->parts_used)<p class="mt-2 whitespace-pre-line text-xs text-slate-700"><strong>{{ __('ui.parts_used') }}:</strong> {{ $inspection->parts_used }} @if ($inspection->parts_quantity) · {{ __('ui.quantity') }}: {{ $inspection->parts_quantity }} @endif</p>@endif
                            @if ($inspection->testing_details)<p class="mt-2 whitespace-pre-line text-xs text-slate-700"><strong>{{ __('ui.testing_details') }}:</strong> {{ $inspection->testing_details }}</p>@endif
                            @if ($inspection->repair_completed_at)<p class="mt-2 text-xs text-slate-700"><strong>{{ __('ui.repair_completed_date') }}:</strong> {{ $inspection->repair_completed_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</p>@endif
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">{{ __('ui.no_inspection_records') }}</p>
                    @endforelse
                </div>
            </section>

            @if (auth()->user()->can('quotations.view'))
                <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-slate-900">{{ __('ui.related_quotations') }}</h2>
                        @can('create', \App\Modules\Quotations\Models\Quotation::class)
                            <a href="{{ route('quotations.create', ['repair_job_id' => $job->id]) }}" class="rounded-lg bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.new_quotation') }}</a>
                        @endcan
                    </div>
                    <div class="space-y-3">
                        @forelse ($job->quotations ?? [] as $quotation)
                            @if (auth()->user()->can('view', $quotation))
                                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
                                    <div><a href="{{ route('quotations.show', $quotation) }}" class="text-xs font-semibold text-teal-800 hover:underline">{{ $quotation->quotation_number }}</a><p class="mt-1 text-[11px] text-slate-500">{{ __('ui.preparation_'.$quotation->preparation_status->value) }} · {{ __('ui.approval_'.$quotation->approval_status->value) }}</p></div>
                                    <a href="{{ route('quotations.show', $quotation) }}" class="text-xs font-semibold text-slate-600 hover:text-teal-800">{{ __('ui.view') }}</a>
                                </article>
                            @endif
                        @empty
                            <p class="text-xs text-slate-500">{{ __('ui.no_quotations_for_job') }}</p>
                        @endforelse
                    </div>
                </section>
            @endif

            @can('inspect', $job)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.record_inspection') }}</h2>
                    <form method="POST" action="{{ route('repair-jobs.inspect', $job) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="form-label" for="inspected_at">{{ __('ui.inspection_date') }}</label>
                            <input id="inspected_at" type="datetime-local" name="inspected_at" value="{{ old('inspected_at') }}" class="form-input">
                            @error('inspected_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="existing_fault">{{ __('ui.existing_fault') }}</label>
                            <textarea id="existing_fault" name="existing_fault" rows="2" class="form-input">{{ old('existing_fault', $job->reported_fault) }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="inspection_result">{{ __('ui.inspection_result') }} <span class="text-red-600">*</span></label>
                            <textarea id="inspection_result" name="inspection_result" rows="4" required class="form-input">{{ old('inspection_result') }}</textarea>
                            @error('inspection_result')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="technical_information">{{ __('ui.technical_information') }}</label>
                            <textarea id="technical_information" name="technical_information" rows="3" class="form-input">{{ old('technical_information') }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="inspection_notes">{{ __('ui.special_notes') }}</label>
                            <textarea id="inspection_notes" name="special_notes" rows="2" class="form-input">{{ old('special_notes') }}</textarea>
                        </div>
                        <div class="sm:col-span-2"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.save_inspection') }}</button></div>
                    </form>
                </section>
            @endcan

            @can('repair', $job)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                    <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.repair_information') }}</h2>
                    <form method="POST" action="{{ route('repair-jobs.save-repair', $job) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="form-label" for="work_performed">{{ __('ui.work_performed') }} <span class="text-red-600">*</span></label>
                            <textarea id="work_performed" name="work_performed" rows="4" required class="form-input">{{ old('work_performed') }}</textarea>
                            @error('work_performed')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="parts_used">{{ __('ui.parts_used') }}</label>
                            <textarea id="parts_used" name="parts_used" rows="3" class="form-input">{{ old('parts_used') }}</textarea>
                        </div>
                        <div>
                            <label class="form-label" for="parts_quantity">{{ __('ui.quantity') }}</label>
                            <input id="parts_quantity" name="parts_quantity" type="number" min="0" step="0.001" value="{{ old('parts_quantity') }}" class="form-input">
                            @error('parts_quantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="parts_cost">{{ __('ui.total_spare_parts_cost') }}</label>
                            <input id="parts_cost" name="parts_cost" type="number" min="0" step="0.01" value="{{ old('parts_cost', $job->parts_cost) }}" class="form-input">
                            @error('parts_cost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="repair_labor_cost">{{ __('ui.labor_cost') }}</label>
                            <input id="repair_labor_cost" name="labor_cost" type="number" min="0" step="0.01" value="{{ old('labor_cost', $job->labour_cost) }}" class="form-input">
                            @error('labor_cost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="repair_completed_at">{{ __('ui.repair_completed_date') }}</label>
                            <input id="repair_completed_at" name="repair_completed_at" type="datetime-local" value="{{ old('repair_completed_at') }}" class="form-input">
                            @error('repair_completed_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="testing_details">{{ __('ui.testing_details') }}</label>
                            <textarea id="testing_details" name="testing_details" rows="3" class="form-input">{{ old('testing_details') }}</textarea>
                        </div>
                        <div>
                            <label class="form-label" for="repair_notes">{{ __('ui.completion_notes') }}</label>
                            <textarea id="repair_notes" name="special_notes" rows="3" class="form-input">{{ old('special_notes') }}</textarea>
                        </div>
                        <p class="sm:col-span-2 text-[11px] text-slate-500">{{ __('ui.no_stock_deduction_notice') }}</p>
                        <div class="sm:col-span-2"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.save_repair_information') }}</button></div>
                    </form>
                </section>
            @endcan
            @endif
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.job_financial_snapshot') }}</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="form-label">{{ __('ui.advance_paid') }}</dt><dd class="font-semibold text-slate-800">{{ config('business.currency') }} {{ number_format((float) $advancePaid, 2) }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.total_amount') }}</dt><dd class="text-slate-700">{{ in_array($job->status->value, ['Repair Completed', 'Ready for Collection', 'Delivered']) ? config('business.currency').' '.$job->total_job_amount : __('ui.amount_unavailable') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.outstanding_balance') }}</dt><dd class="text-slate-700">{{ __('ui.amount_unavailable') }}</dd></div>
                </dl>
                @can('payments.view')
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="mb-3 text-xs font-bold text-slate-800">{{ __('payments.advance_history') }}</h3>
                        <div class="space-y-3">
                            @forelse ($advancePayments as $allocation)
                                @php($releasedAdvance = $allocation->releases->reduce(fn ($sum, $release) => $sum->plus((string) $release->amount), \Brick\Math\BigDecimal::zero()->toScale(2)))
                                @php($netAdvance = \Brick\Math\BigDecimal::of((string) $allocation->amount)->minus($releasedAdvance)->toScale(2))
                                <a href="{{ route('payments.show', $allocation->payment) }}" class="flex items-center justify-between gap-3 text-xs hover:text-teal-800">
                                    <span><span class="block font-semibold">{{ $allocation->payment->payment_number }}</span><span class="mt-1 block text-[10px] text-slate-500">{{ $allocation->payment->paid_at->format(config('business.date_format')) }} · {{ __('payments.method_'.$allocation->payment->method->value) }}</span></span>
                                    <span class="font-semibold">{{ config('business.currency') }} {{ $netAdvance }}</span>
                                </a>
                            @empty
                                <p class="text-xs text-slate-500">{{ __('payments.no_job_advances') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endcan
            </section>
            @can('updateStatus', $job)
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.update_job_status') }}</h2>
                    <form method="POST" action="{{ route('repair-jobs.update-status', $job) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="form-label" for="status">{{ __('ui.job_status') }}</label>
                            <select id="status" name="status" class="form-input" required>
                                @foreach ($statuses as $status)
                                    @if (($status !== \App\Enums\JobStatus::Cancelled || auth()->user()->can('jobs.cancel')) && ($status !== \App\Enums\JobStatus::RepairCompleted || auth()->user()->can('jobs.complete')))
                                        <option value="{{ $status->value }}" @selected($job->status === $status)>{{ $status->value }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="status_notes">{{ __('ui.status_notes') }}</label>
                            <textarea id="status_notes" name="notes" rows="3" class="form-input">{{ old('notes') }}</textarea>
                        </div>
                        @can('jobs.cancel')
                            <div><label class="form-label" for="cancellation_reason">{{ __('ui.cancellation_reason') }}</label><textarea id="cancellation_reason" name="cancellation_reason" rows="2" class="form-input">{{ old('cancellation_reason') }}</textarea></div>
                        @endcan
                        <button class="w-full rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.save_status') }}</button>
                    </form>
                </section>
            @endcan
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.job_status_history') }}</h2>
                <ol class="space-y-4">
                    @forelse ($job->jobStatusHistories as $history)
                        <li class="border-l-2 border-teal-200 pl-3">
                            <p class="text-xs font-semibold text-slate-800">{{ $history->new_status->value }}</p>
                            @if ($history->previous_status)<p class="mt-1 text-[10px] text-slate-500">{{ __('ui.previous_status') }}: {{ $history->previous_status->value }}</p>@endif
                            <p class="mt-1 text-[10px] text-slate-500">{{ $history->changed_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }} · {{ $history->changedByUser?->name ?: '—' }}</p>
                            @if ($history->notes)<p class="mt-1 whitespace-pre-line text-[10px] text-slate-600">{{ $history->notes }}</p>@endif
                        </li>
                    @empty
                        <li class="text-xs text-slate-500">{{ __('ui.no_status_history') }}</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>
@endsection
