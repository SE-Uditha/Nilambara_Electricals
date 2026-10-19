@extends('layouts.app')

@section('title', 'Register employee')

@section('content')
    <div class="mb-7">
        <a href="{{ route('employees.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Employees</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Register employee</h1>
        <p class="mt-1 text-sm text-slate-500">Employee details are stored separately from login credentials.</p>
    </div>
    @include('modules.employees.form', ['action' => route('employees.store'), 'method' => 'POST'])
@endsection
