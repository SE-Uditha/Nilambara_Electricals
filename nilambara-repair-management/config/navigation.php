<?php

return [
    [
        'label' => 'workspace',
        'items' => [
            ['label' => 'dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
        ],
    ],
    [
        'label' => 'repair_management',
        'items' => [
            ['label' => 'customers', 'icon' => 'users', 'route' => 'customers.index', 'permission' => 'customers.view'],
            ['label' => 'repair_receipts', 'icon' => 'receipt', 'route' => 'repair-receipts.index', 'permission' => 'receipts.view'],
            ['label' => 'new_repair_receipt', 'icon' => 'receipt', 'route' => 'repair-receipts.create', 'permission' => 'receipts.create'],
            ['label' => 'repair_jobs', 'icon' => 'tool', 'route' => 'repair-jobs.index', 'permission' => 'jobs.view', 'except_roles' => ['technician']],
            ['label' => 'my_assigned_jobs', 'icon' => 'tool', 'route' => 'repair-jobs.assigned', 'permission' => 'jobs.view', 'roles' => ['technician']],
            ['label' => 'job_cards', 'icon' => 'clipboard', 'route' => 'job-cards.index', 'permission' => 'job-cards.view'],
            ['label' => 'quotations', 'icon' => 'document', 'route' => 'quotations.index', 'permission' => 'quotations.view'],
            ['label' => 'job_status', 'icon' => 'activity', 'route' => 'repair-jobs.status-search', 'permission' => 'jobs.view'],
        ],
    ],
    [
        'label' => 'sales_finance',
        'items' => [
            ['label' => 'invoices', 'icon' => 'invoice', 'permission' => 'invoices.view'],
            ['label' => 'payments', 'icon' => 'wallet', 'permission' => 'payments.view'],
            ['label' => 'credit_management', 'icon' => 'credit', 'permission' => 'credit.view'],
            ['label' => 'pos', 'icon' => 'pos', 'permission' => 'pos.view'],
            ['label' => 'cash_summary', 'icon' => 'wallet', 'permission' => 'reports.view', 'roles' => ['administrator', 'cashier', 'manager']],
        ],
    ],
    [
        'label' => 'stock_purchasing',
        'items' => [
            ['label' => 'inventory', 'icon' => 'box', 'permission' => 'inventory.view'],
            ['label' => 'suppliers', 'icon' => 'truck', 'permission' => 'suppliers.view'],
            ['label' => 'purchases', 'icon' => 'bag', 'permission' => 'purchases.view'],
            ['label' => 'stock_issues', 'icon' => 'box', 'permission' => 'inventory.issue', 'roles' => ['administrator', 'storekeeper']],
        ],
    ],
    [
        'label' => 'administration',
        'items' => [
            ['label' => 'employees', 'icon' => 'badge', 'route' => 'employees.index', 'permission' => 'employees.view'],
            ['label' => 'users', 'icon' => 'users', 'route' => 'admin.users.index', 'permission' => 'users.view'],
            ['label' => 'roles_permissions', 'icon' => 'shield', 'route' => 'admin.roles.index', 'permission' => 'roles.view'],
            ['label' => 'permissions', 'icon' => 'lock', 'route' => 'admin.permissions.index', 'permission' => 'permissions.manage'],
            ['label' => 'approvals', 'icon' => 'shield', 'permission' => 'purchases.approve', 'roles' => ['administrator', 'manager']],
            ['label' => 'reports', 'icon' => 'chart', 'permission' => 'reports.view'],
            ['label' => 'backup_restore', 'icon' => 'database', 'permission' => 'backups.view'],
            ['label' => 'system_settings', 'icon' => 'settings', 'permission' => 'settings.view'],
        ],
    ],
];
