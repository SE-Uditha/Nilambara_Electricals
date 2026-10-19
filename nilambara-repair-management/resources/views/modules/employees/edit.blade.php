@extends('layouts.app')

@section('title', 'Edit employee')

@section('content')
    <div class="mb-7">
        <a href="{{ route('employees.show', $employee) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← Employee details</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Edit employee</h1>
        <p class="mt-1 text-sm text-slate-500">Employee ID <span class="font-mono">{{ $employee->employee_code }}</span> cannot be changed.</p>
    </div>
    @include('modules.employees.form', ['action' => route('employees.update', $employee), 'method' => 'PUT'])
@endsection
