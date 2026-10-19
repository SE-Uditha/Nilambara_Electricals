<?php

namespace Database\Seeders;

use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'administrator' => 'Administrator',
            'receptionist' => 'Receptionist',
            'technician' => 'Technician',
            'cashier' => 'Cashier',
            'storekeeper' => 'Storekeeper',
            'manager' => 'Manager',
        ];

        foreach ($roles as $name => $label) {
            Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'code' => $name,
                    'label' => $label,
                    'description' => "{$label} role for the repair management system.",
                    'is_active' => true,
                ],
            );
        }

        $permissionsByModule = [
            'dashboard' => ['view'],
            'customers' => ['view', 'create', 'update', 'deactivate', 'statement'],
            'receipts' => ['view', 'create', 'print', 'reprint'],
            'jobs' => ['view', 'create', 'update', 'assign', 'inspect', 'repair', 'update-status', 'complete', 'cancel'],
            'job-cards' => ['view', 'print'],
            'quotations' => ['view', 'create', 'update', 'issue', 'approve', 'revise', 'cancel', 'print'],
            'invoices' => ['view', 'create', 'print', 'cancel'],
            'payments' => ['view', 'create', 'receive', 'reverse'],
            'credit' => ['view', 'collect', 'reports'],
            'inventory' => ['view', 'create', 'update', 'issue', 'adjust', 'stock-count'],
            'suppliers' => ['view', 'create', 'update'],
            'purchases' => ['view', 'create', 'receive', 'approve'],
            'pos' => ['view', 'sale', 'refund'],
            'employees' => ['view', 'create', 'update', 'deactivate'],
            'users' => ['view', 'create', 'update', 'disable'],
            'roles' => ['view', 'manage'],
            'permissions' => ['manage'],
            'reports' => ['view', 'export', 'financial'],
            'settings' => ['view', 'update'],
            'backups' => ['view', 'create', 'restore'],
            'audit' => ['view'],
        ];

        foreach ($permissionsByModule as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $label = Str::of($name)->replace(['-', '.'], ' ')->headline()->toString();

                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'code' => $name,
                        'label' => $label,
                        'module' => $module,
                        'description' => "{$label} access.",
                    ],
                );
            }
        }

        $rolePermissions = [
            'receptionist' => [
                'dashboard.view', 'customers.view', 'customers.create', 'customers.update',
                'receipts.view', 'receipts.create', 'receipts.print', 'jobs.view', 'jobs.create',
                'job-cards.view', 'job-cards.print',
            ],
            'technician' => [
                'dashboard.view', 'jobs.view', 'jobs.inspect', 'jobs.repair', 'jobs.update-status',
                'jobs.complete', 'job-cards.view',
            ],
            'cashier' => [
                'dashboard.view', 'invoices.view', 'invoices.create', 'invoices.print',
                'payments.view', 'payments.create', 'payments.receive', 'pos.view', 'pos.sale',
                'reports.view',
            ],
            'storekeeper' => [
                'dashboard.view', 'inventory.view', 'inventory.create', 'inventory.update',
                'inventory.issue', 'inventory.adjust', 'inventory.stock-count',
                'purchases.view', 'purchases.create', 'purchases.receive',
            ],
            'manager' => [
                'dashboard.view', 'jobs.view', 'jobs.assign', 'quotations.view', 'quotations.approve',
                'invoices.view', 'payments.view', 'credit.view', 'credit.collect', 'credit.reports',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'purchases.view',
                'purchases.create', 'purchases.receive', 'purchases.approve', 'reports.view',
                'reports.export', 'reports.financial',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            if ($role->permissions()->exists()) {
                continue;
            }

            $role->givePermissionTo($permissionNames);
        }

        $quotationGrants = [
            'receptionist' => ['quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue', 'quotations.print'],
            'technician' => ['quotations.view'],
            'cashier' => ['quotations.view', 'quotations.print'],
            'manager' => [
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue',
                'quotations.approve', 'quotations.revise', 'quotations.cancel', 'quotations.print',
            ],
        ];
        foreach ($quotationGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        Role::query()->whereIn('name', ['manager', 'cashier'])
            ->each(function (Role $role): void {
                $role->givePermissionTo($role->name === 'manager'
                    ? ['customers.view', 'customers.statement']
                    : ['customers.view']);
            });

        Role::query()->where('name', 'administrator')->firstOrFail()
            ->syncPermissions(Permission::query()->get());
    }
}
