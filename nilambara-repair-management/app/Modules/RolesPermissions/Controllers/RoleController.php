<?php

namespace App\Modules\RolesPermissions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('modules.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        return view('modules.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ]);

        $permissionIds = array_map('intval', $data['permissions'] ?? []);

        if ($role->name === 'administrator') {
            $required = Permission::query()
                ->pluck('id')
                ->all();

            if (array_diff($required, $permissionIds) !== []) {
                return back()->withErrors(['permissions' => __('The Administrator role always receives every available permission.')])
                    ->withInput();
            }
        }

        $role->syncPermissions($permissionIds);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'role.permissions.changed',
            'entity_type' => Role::class,
            'entity_id' => $role->id,
            'occurred_at' => now(),
            'new_values' => ['permissions' => $permissionIds],
            'ip_address' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return redirect()->route('admin.roles.index')->with('status', __('Role permissions updated.'));
    }
}
