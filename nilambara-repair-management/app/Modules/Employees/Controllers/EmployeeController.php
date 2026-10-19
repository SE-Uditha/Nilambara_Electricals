<?php

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Services\EmployeeRegistrationService;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::query()->with('user.roles')->orderBy('employee_code');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->whereHas('user.roles', fn ($builder) => $builder->where('roles.name', $role));
        }

        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('modules.employees.index', [
            'employees' => $query->paginate(20)->withQueryString(),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('label')->get(),
        ]);
    }

    public function create(): View
    {
        return view('modules.employees.create', [
            'employee' => new Employee(['is_active' => true]),
            'roles' => Role::query()->where('guard_name', 'web')->where('is_active', true)->orderBy('label')->get(),
            'canCreateAccount' => auth()->user()->can('users.create') && auth()->user()->can('roles.manage'),
        ]);
    }

    public function store(
        StoreEmployeeRequest $request,
        EmployeeRegistrationService $registration,
    ): RedirectResponse {
        $employee = $registration->register($request->validated(), $request->user());

        return redirect()->route('employees.show', $employee)
            ->with('status', __('Employee registered successfully.'));
    }

    public function show(Employee $employee): View
    {
        $employee->load('user.roles');

        $overrides = $employee->user?->permissions()
            ->withPivot('effect')
            ->get()
            ->keyBy('id') ?? collect();

        return view('modules.employees.show', [
            'employee' => $employee,
            'overrides' => $overrides,
            'recentActions' => $employee->user
                ? $employee->user->auditLogs()->latest('occurred_at')->limit(10)->get()
                : AuditLog::query()
                    ->where('entity_type', Employee::class)
                    ->where('entity_id', $employee->id)
                    ->latest('occurred_at')
                    ->limit(10)
                    ->get(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('modules.employees.edit', [
            'employee' => $employee->load('user.roles'),
        ]);
    }

    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        EmployeeRegistrationService $registration,
    ): RedirectResponse {
        $changes = $request->validated();

        if ($employee->is_active && ! $changes['is_active'] && ! $request->user()->can('employees.deactivate')) {
            abort(403);
        }

        $registration->update($employee, $changes, $request->user());

        return redirect()->route('employees.show', $employee)
            ->with('status', __('Employee details updated.'));
    }

    public function permissions(Employee $employee): View
    {
        abort_unless($employee->user && auth()->user()->can('permissions.manage'), 404);

        return view('modules.employees.permissions', [
            'employee' => $employee->load('user.roles'),
            'permissions' => Permission::query()
                ->orderBy('module')
                ->orderBy('name')
                ->get()
                ->groupBy('module'),
            'overrides' => $employee->user->permissions()->withPivot('effect')->get()->keyBy('id'),
        ]);
    }
}
