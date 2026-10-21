@extends('layouts.app')
@section('title', 'My approval requests')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">My approval requests</h1><a href="{{ route('approval-requests.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white">New request</a></div>
@include('modules.inventory.feedback')
<div class="rounded-xl border bg-white p-5">@include('modules.approvals.table')</div>
@endsection
