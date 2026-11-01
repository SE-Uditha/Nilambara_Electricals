@extends('layouts.app')

@section('title','Repair part requests and issues')

@section('content')
<h1 class="mb-5 text-2xl font-bold">Repair part requests and issues</h1>
@include('modules.inventory.feedback')

<p class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">Add and edit job parts from the Job Details page. Stock is deducted only when a job is marked Repair Completed.</p>
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Job</th><th>Part / location</th><th>Quantity</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($usages as $usage)<tr class="border-t"><td class="p-3">{{ $usage->repairJob->job_number }}</td><td>{{ $usage->sparePart->name }} · {{ $usage->location->name }}</td><td>{{ $usage->quantity }}</td><td>{{ $usage->status }}</td><td>
@if(in_array($usage->status,['draft','approved','issued']))
@if($usage->status==='issued')@can('inventory.issue')
<form method="POST" action="{{ route('stock-issues.cancel',$usage) }}" class="mt-2">
@csrf
<label>Reason<input class="form-input w-full" name="reason" minlength="5" required></label>
<button class="text-red-700" onclick="return confirm('Confirm all issued parts have physically returned in resalable condition.')">Return entire unused issue</button></form>
@endcan
@elseif(in_array($usage->status,['draft','approved']))@can('jobs.repair')
<form method="POST" action="{{ route('stock-issues.cancel',$usage) }}" class="mt-2">
@csrf
<label>Reason<input class="form-input w-full" name="reason" minlength="5" required></label>
<button class="text-red-700">Cancel request</button></form>
@endcan
@endif
@endif</td></tr>
@empty<tr><td class="p-4" colspan="5">No requests found.</td></tr>
@endforelse</tbody></table></div>{{ $usages->links() }}

@endsection
