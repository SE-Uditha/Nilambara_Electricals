<?php

namespace Database\Seeders;

use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use App\Modules\RolesPermissions\Services\MergeOperationsIntoCashier;
use App\Modules\RolesPermissions\Services\MergeReceptionistIntoCashier;
use App\Modules\RolesPermissions\Services\MergeTechnicianIntoCashier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(MergeReceptionistIntoCashier::class)->run();
        app(MergeOperationsIntoCashier::class)->run();
        app(MergeTechnicianIntoCashier::class)->run();

        $roles = [
            'administrator' => 'Administrator',
            'cashier' => 'Cashier',
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
            'jobs' => ['view', 'create', 'update', 'assign', 'inspect', 'repair', 'update-status', 'complete', 'cancel', 'delete'],
            'job-cards' => ['view', 'print'],
            'quotations' => ['view', 'create', 'update', 'issue', 'approve', 'revise', 'cancel', 'print'],
            'invoices' => ['view', 'create', 'update-draft', 'issue', 'print', 'cancel', 'export'],
            'payments' => ['view', 'create', 'receive', 'receive-for-others', 'reverse', 'print', 'export'],
            'credit' => ['view', 'collect', 'reports'],
            'inventory' => ['view', 'create', 'update', 'issue', 'adjust', 'stock-count'],
            'suppliers' => ['view', 'create', 'update'],
            'purchases' => ['view', 'create', 'receive', 'approve'],
            'employees' => ['view', 'create', 'update', 'deactivate'],
            'users' => ['view', 'create', 'update', 'disable'],
            'roles' => ['view', 'manage'],
            'permissions' => ['manage'],
            'reports' => ['view', 'export', 'financial', 'repair', 'sales', 'inventory', 'credit'],
            'settings' => ['view', 'update'],
            'print-templates' => ['view', 'update', 'reset'],
            'backups' => ['view', 'create', 'restore', 'download', 'configure'],
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
            'cashier' => [
                'jobs.update-status',
                'jobs.repair',
                'jobs.inspect',
                'jobs.complete',
                'credit.collect',
                'credit.reports',
                'credit.view',
                'customers.create',
                'customers.statement',
                'customers.view',
                'dashboard.view',
                'inventory.adjust',
                'inventory.create',
                'inventory.issue',
                'inventory.stock-count',
                'inventory.update',
                'inventory.view',
                'invoices.cancel',
                'invoices.create',
                'invoices.export',
                'invoices.issue',
                'invoices.print',
                'invoices.update-draft',
                'invoices.view',
                'job-cards.print',
                'job-cards.view',
                'jobs.assign',
                'jobs.create',
                'jobs.view',
                'payments.create',
                'payments.export',
                'payments.print',
                'payments.receive',
                'payments.reverse',
                'payments.view',
                'purchases.approve',
                'purchases.create',
                'purchases.receive',
                'purchases.view',
                'quotations.approve',
                'quotations.cancel',
                'quotations.create',
                'quotations.issue',
                'quotations.print',
                'quotations.revise',
                'quotations.update',
                'quotations.view',
                'receipts.create',
                'receipts.print',
                'receipts.view',
                'reports.credit',
                'reports.export',
                'reports.financial',
                'reports.inventory',
                'reports.repair',
                'reports.sales',
                'reports.view',
                'suppliers.create',
                'suppliers.update',
                'suppliers.view',
            ],
        ];
        foreach ($rolePermissions as $name => $permissions) {
            Role::query()->where('name', $name)->firstOrFail()->givePermissionTo($permissions);
        }

        Role::query()->where('name', 'administrator')->firstOrFail()
            ->syncPermissions(Permission::query()->get());
    }
}
