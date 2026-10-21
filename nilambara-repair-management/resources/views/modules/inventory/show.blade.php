@extends('layouts.app')

@section('title',$part->name)

@section('content')
<a class="mb-4 inline-block text-sm text-teal-700 underline" href="{{ route('approval-requests.create',['kind'=>'stock_adjustment','subject_id'=>$part->id]) }}">Request stock correction / write-off approval</a>
<div class="mb-5 flex justify-between"><h1 class="text-2xl font-bold">{{ $part->name }} · {{ $part->sku }}</h1>
@can('inventory.update')<a class="text-teal-700 underline" href="{{ route('inventory.edit',$part) }}">Edit part</a>
@endcan</div>
@include('modules.inventory.feedback')
<p class="mb-5">{{ $part->description }} · {{ $part->unit->name }} · Selling price LKR {{ $part->selling_price }}</p>
<div class="grid gap-4 md:grid-cols-2">
@foreach(['adjust'=>'inventory.adjust','count'=>'inventory.stock-count'] as $operation=>$permission)
@can($permission)<form method="POST" action="{{ route('inventory.operation',['part'=>$part,'operation'=>$operation]) }}" class="grid gap-3 rounded-2xl border bg-white p-5">
@csrf<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><h2 class="font-bold">{{ $operation==='count' ? 'Physical stock count' : 'Stock correction / damage' }}</h2><label>Location<select class="form-input w-full" name="location_id">
@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>
@endforeach</select></label><label>{{ $operation==='count' ? 'Actual counted quantity' : 'Quantity change (negative removes stock)' }}<input class="form-input w-full" name="quantity" required></label><label>Type<select class="form-input w-full" name="adjustment_type"><option value="correction">Correction</option>
@if($operation==='adjust')<option value="damaged">Damaged stock</option>
@endif</select></label><label>Reason<input class="form-input w-full" name="reason" minlength="5" required></label><button class="rounded-xl bg-teal-700 p-3 text-white" onclick="return confirm('Post this stock operation? It will be kept in history.')">Post {{ $operation }}</button></form>
@endcan
@endforeach</div>
<h2 class="mt-7 mb-3 text-lg font-bold">Stock movement history</h2><div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Date</th><th>Location</th><th>Type</th><th>Quantity</th><th>Reference</th><th>Employee / reason</th></tr></thead><tbody>
@foreach($movements as $movement)<tr class="border-t"><td class="p-3">{{ $movement->occurred_at }}</td><td>{{ $movement->location->name }}</td><td>{{ $movement->movement_type }}</td><td>{{ $movement->quantity_delta }}</td><td>{{ $movement->reference_number }}</td><td>{{ $movement->recordedByUser->name }} · {{ $movement->reason }}</td></tr>
@endforeach</tbody></table></div>{{ $movements->links() }}

@endsection
