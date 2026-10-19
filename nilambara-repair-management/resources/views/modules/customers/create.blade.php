@extends('layouts.app')

@section('title', __('ui.register_customer'))

@section('content')
    <div class="mb-7">
        <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('ui.customers') }}</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.register_customer') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.customer_create_description') }}</p>
    </div>
    @include('modules.customers.form', ['customer' => null, 'action' => route('customers.store'), 'method' => 'POST'])
@endsection
