<?php

namespace App\Modules\Employees\Services;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeRegistrationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor): Employee {
            $employee = Employee::query()->create([
                'employee_code' => 'EMP-'.Str::upper((string) Str::ulid()),
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'mobile_number' => $data['mobile_number'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'date_joined' => $data['date_joined'] ?? null,
                'is_active' => (bool) $data['is_active'],
            ]);

            if (($data['create_account'] ?? false) === true || ($data['create_account'] ?? null) === '1') {
                $user = User::query()->create([
                    'name' => $employee->name,
                    'username' => Str::lower($data['username']),
                    'email' => $data['email'] ?? null,
                    'password' => $data['password'],
                    'is_active' => $employee->is_active,
                ]);

                $employee->user()->associate($user);
                $employee->save();

                $role = Role::query()->findOrFail($data['role_id']);
                $user->syncRoles([$role]);

                $this->audit($actor, 'user.account.created', $user, [
                    'username' => $user->username,
                    'role' => $role->name,
                ]);
            }

            $this->audit($actor, 'employee.created', $employee, [
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'has_login' => $employee->user_id !== null,
            ]);

            return $employee->load('user.roles');
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(Employee $employee, array $changes, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $changes, $actor): Employee {
            $before = $employee->only([
                'name', 'address', 'mobile_number', 'job_title', 'department', 'date_joined', 'is_active',
            ]);

            $employee->fill($changes);
            $employee->save();

            if (! $employee->is_active && $employee->user !== null) {
                app(UserAccountService::class)->setEnabled($employee->user, false, $actor);
            }

            $this->audit($actor, 'employee.updated', $employee, [
                'before' => $before,
                'after' => $employee->only(array_keys($before)),
            ]);

            return $employee->fresh('user.roles');
        });
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(User $actor, string $action, Employee|User $entity, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
            'occurred_at' => now(),
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
        ]);
    }
}
