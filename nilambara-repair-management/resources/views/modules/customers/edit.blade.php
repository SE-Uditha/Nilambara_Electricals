@extends('layouts.app')

@section('title', __('ui.edit_customer'))

@section('content')
    <div class="mb-7">
        <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ $customer->customer_code }}</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.edit_customer') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $customer->display_name }}</p>
    </div>
    @include('modules.customers.form', ['action' => route('customers.update', $customer), 'method' => 'PUT'])
@endsection
