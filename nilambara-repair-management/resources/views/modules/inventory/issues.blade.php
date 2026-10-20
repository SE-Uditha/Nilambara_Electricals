@extends('layouts.app')

@section('title','Repair part requests and issues')

@section('content')
<h1 class="mb-5 text-2xl font-bold">Repair part requests and issues</h1>
@include('modules.inventory.feedback')

@can('jobs.repair')<form method="POST" action="{{ route('stock-issues.store') }}" class="mb-6 grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-2">
@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)Illuminate\Support\Str::uuid()) }}"><label>Repair job<select class="form-input w-full" name="repair_job_id">
@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->job_number }} · {{ $job->item_name }}</option>
@endforeach</select></label><label>Part<select class="form-input w-full" name="spare_part_id">
@foreach($parts as $part)<option value="{{ $part->id }}">{{ $part->sku }} · {{ $part->name }}</option>
@endforeach</select></label><label>Location<select class="form-input w-full" name="location_id">
@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>
@endforeach</select></label><label>Quantity<input class="form-input w-full" name="quantity" required></label><label>Notes<input class="form-input w-full" name="notes"></label><button class="rounded-xl bg-teal-700 p-3 text-white">Request part</button></form>
@endcan
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Job</th><th>Part / location</th><th>Quantity</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($usages as $usage)<tr class="border-t"><td class="p-3">{{ $usage->repairJob->job_number }}</td><td>{{ $usage->sparePart->name }} · {{ $usage->location->name }}</td><td>{{ $usage->quantity }}</td><td>{{ $usage->status }}</td><td>
@can('inventory.issue')
@if(in_array($usage->status,['draft','approved']))<form method="POST" action="{{ route('stock-issues.issue',$usage) }}">
@csrf<button class="rounded-lg bg-teal-700 p-2 text-white" onclick="return confirm('Approve and deduct this quantity from stock?')">Approve and issue</button></form>
@endif
@if(in_array($usage->status,['draft','approved','issued']))
<form method="POST" action="{{ route('stock-issues.cancel',$usage) }}" class="mt-2">
@csrf
<label>Reason<input class="form-input w-full" name="reason" minlength="5" required></label>
<button class="text-red-700" onclick="return confirm('Cancel this request? For an issued request, confirm all unused parts have physically returned in resalable condition.')">{{ $usage->status==='issued' ? 'Return entire unused issue' : 'Cancel request' }}</button></form>
@endif
@endcan</td></tr>
@empty<tr><td class="p-4" colspan="5">No requests found.</td></tr>
@endforelse</tbody></table></div>{{ $usages->links() }}

@endsection
