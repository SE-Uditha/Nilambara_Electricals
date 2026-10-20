<?php

namespace Database\Seeders;

use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use App\Modules\RolesPermissions\Services\MergeReceptionistIntoCashier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(MergeReceptionistIntoCashier::class)->run();

        $roles = [
            'administrator' => 'Administrator',
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
            'invoices' => ['view', 'create', 'update-draft', 'issue', 'print', 'cancel', 'export'],
            'payments' => ['view', 'create', 'receive', 'receive-for-others', 'reverse', 'print', 'export'],
            'credit' => ['view', 'collect', 'reports'],
            'inventory' => ['view', 'create', 'update', 'issue', 'adjust', 'stock-count'],
            'suppliers' => ['view', 'create', 'update'],
            'purchases' => ['view', 'create', 'receive', 'approve'],
            'pos' => ['view', 'sale', 'refund', 'sales.view', 'receipt.print', 'discount', 'sale.cancel', 'return', 'payment.verify', 'reports.view'],
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
            'technician' => [
                'dashboard.view', 'jobs.view', 'jobs.inspect', 'jobs.repair', 'jobs.update-status',
                'jobs.complete', 'job-cards.view',
            ],
            'cashier' => [
                'customers.create', 'customers.update', 'receipts.view', 'receipts.create', 'receipts.print',
                'jobs.view', 'jobs.create', 'job-cards.view', 'job-cards.print',
                'dashboard.view', 'invoices.view', 'invoices.create', 'invoices.print',
                'payments.view', 'payments.create', 'payments.receive', 'payments.print', 'payments.export',
                'credit.view', 'credit.collect', 'pos.view', 'pos.sale', 'pos.sales.view', 'pos.receipt.print',
                'reports.view',
            ],
            'storekeeper' => [
                'dashboard.view', 'inventory.view', 'inventory.create', 'inventory.update',
                'inventory.issue', 'inventory.adjust', 'inventory.stock-count',
                'purchases.view', 'purchases.create', 'purchases.receive',
            ],
            'manager' => [
                'dashboard.view', 'jobs.view', 'jobs.assign', 'quotations.view', 'quotations.approve',
                'invoices.view', 'invoices.create', 'invoices.update-draft', 'invoices.issue',
                'invoices.print', 'invoices.cancel', 'invoices.export',
                'payments.view', 'payments.create', 'payments.receive', 'payments.reverse',
                'payments.print', 'payments.export', 'credit.view', 'credit.collect', 'credit.reports',
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

        $reportGrants = [
            'technician' => ['reports.view', 'reports.repair'],
            'cashier' => ['reports.sales', 'reports.repair'],
            'storekeeper' => ['reports.view', 'reports.inventory', 'reports.export'],
            'manager' => ['reports.repair', 'reports.sales', 'reports.inventory', 'reports.credit'],
        ];
        foreach ($reportGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        $quotationGrants = [
            'technician' => ['quotations.view'],
            'cashier' => ['quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue', 'quotations.print'],
            'manager' => [
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.issue',
                'quotations.approve', 'quotations.revise', 'quotations.cancel', 'quotations.print',
            ],
        ];
        foreach ($quotationGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        $invoiceGrants = [
            'cashier' => ['invoices.view', 'invoices.create', 'invoices.update-draft', 'invoices.issue', 'invoices.print', 'invoices.export'],
            'manager' => ['invoices.view', 'invoices.create', 'invoices.update-draft', 'invoices.issue', 'invoices.print', 'invoices.cancel', 'invoices.export'],
        ];
        foreach ($invoiceGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        $paymentGrants = [
            'cashier' => [
                'payments.view', 'payments.create', 'payments.receive', 'payments.print', 'payments.export',
                'credit.view', 'credit.collect',
            ],
            'manager' => [
                'payments.view', 'payments.create', 'payments.receive', 'payments.reverse',
                'payments.print', 'payments.export', 'credit.view', 'credit.collect', 'credit.reports',
            ],
        ];
        foreach ($paymentGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        $posGrants = [
            'cashier' => ['pos.view', 'pos.sale', 'pos.sales.view', 'pos.receipt.print'],
            'manager' => [
                'pos.view', 'pos.sales.view', 'pos.receipt.print', 'pos.discount',
                'pos.sale.cancel', 'pos.return', 'pos.refund', 'pos.payment.verify', 'pos.reports.view',
            ],
            'storekeeper' => ['pos.view'],
        ];
        foreach ($posGrants as $roleName => $permissionNames) {
            Role::query()->where('name', $roleName)->firstOrFail()->givePermissionTo($permissionNames);
        }

        Role::query()->where('name', 'storekeeper')->firstOrFail()->givePermissionTo(['reports.view']);

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
