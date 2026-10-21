@extends('layouts.app')
@section('title', 'Approval request #'.$approval->id)
@section('content')
<div class="mb-5 flex flex-wrap gap-4"><a class="text-sm text-teal-700 underline" href="{{ route('approval-requests.index') }}">My requests</a>@if(\App\Modules\Approvals\Services\ApprovalAccess::reviewKinds(auth()->user()) !== [])<a class="text-sm text-teal-700 underline" href="{{ route('approvals.index') }}">Approval inbox</a>@endif</div>
<h1 class="mb-5 text-2xl font-bold">#{{ $approval->id }} &middot; {{ \App\Modules\Approvals\Services\ApprovalAccess::LABELS[$approval->kind] }}</h1>
@include('modules.inventory.feedback')
<div class="space-y-3 rounded-xl border bg-white p-5"><p>Status: <strong>{{ ucfirst($approval->status) }}</strong></p><p>Requested by: {{ $approval->requester?->name ?? 'Legacy record' }} &middot; {{ $approval->created_at->format('Y-m-d H:i') }}</p><p>Reason: {{ $approval->reason }}</p>@if($approval->amount !== null)<p class="font-bold">Amount: LKR {{ $approval->amount }}</p>@endif
<dl class="grid gap-2 text-sm sm:grid-cols-2">@foreach($approval->payload as $key=>$value)@if(!in_array($key,['idempotency_key','context_hash','reason','kind']) && is_scalar($value))<div><dt class="text-slate-500">{{ ucwords(str_replace('_',' ',$key)) }}</dt><dd class="break-words">{{ $value }}</dd></div>@endif@endforeach</dl>
@if($approval->payment_id)<a class="inline-block text-teal-700 underline" href="{{ route('payments.show',$approval->payment_id) }}">Open related payment</a>@elseif($approval->spare_part_id)<a class="inline-block text-teal-700 underline" href="{{ route('inventory.show',$approval->spare_part_id) }}">Open related stock item</a>@elseif($approval->purchase_id)<a class="inline-block text-teal-700 underline" href="{{ route('purchases.show',$approval->purchase_id) }}">Open purchase</a>@endif
@if($approval->decided_at)<div class="mt-4 border-t pt-4"><p>Reviewed by: {{ $approval->reviewer?->name }} &middot; {{ $approval->decided_at->format('Y-m-d H:i') }}</p><p>Comment: {{ $approval->decision_comment }}</p></div>@endif</div>
@if($approval->status==='pending' && $canDecide)@include('modules.approvals.decision',['decisionAction'=>route('approval-requests.decide',$approval)])@elseif($approval->status==='pending')<p class="mt-4 text-sm text-slate-500">Waiting for another authorized administrator or manager to review this request.</p>@endif
@endsection
