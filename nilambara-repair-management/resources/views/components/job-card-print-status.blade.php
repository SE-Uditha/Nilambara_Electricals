@props(['card'])
@if ($card?->confirmedPrint)
    <span class="inline-flex text-emerald-700" data-job-card-print-confirmed="{{ $card->repair_job_id }}" title="{{ __('printing.confirmed_status') }}"><x-icon name="check-circle" :size="14" /><span class="sr-only">{{ __('printing.confirmed_status') }}</span></span>
@endif
