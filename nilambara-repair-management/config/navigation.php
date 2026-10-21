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
            ['label' => 'customers', 'icon' => 'users', 'active_routes' => ['customers.*'], 'children' => [
                ['label' => 'customers', 'icon' => 'users', 'route' => 'customers.index', 'permission' => 'customers.view'],
                ['label' => 'register_customer', 'icon' => 'users', 'route' => 'customers.create', 'permission' => 'customers.create'],
            ]],
            ['label' => 'repair_receipts', 'icon' => 'receipt', 'active_routes' => ['repair-receipts.*'], 'children' => [
                ['label' => 'repair_receipts', 'icon' => 'receipt', 'route' => 'repair-receipts.index', 'permission' => 'receipts.view'],
                ['label' => 'new_repair_receipt', 'icon' => 'receipt', 'route' => 'repair-receipts.create', 'permission' => 'receipts.create'],
            ]],
            ['label' => 'repair_jobs', 'icon' => 'tool', 'active_routes' => ['repair-jobs.*', 'job-cards.*'], 'children' => [
                ['label' => 'repair_jobs', 'icon' => 'tool', 'route' => 'repair-jobs.index', 'permission' => 'jobs.view', 'except_roles' => ['technician']],
                ['label' => 'my_assigned_jobs', 'icon' => 'tool', 'route' => 'repair-jobs.assigned', 'permission' => 'jobs.view', 'roles' => ['technician']],
                ['label' => 'job_cards', 'icon' => 'clipboard', 'route' => 'job-cards.index', 'permission' => 'job-cards.view'],
                ['label' => 'job_status', 'icon' => 'activity', 'route' => 'repair-jobs.status-search', 'permission' => 'jobs.view'],
            ]],
            ['label' => 'quotations', 'icon' => 'document', 'active_routes' => ['quotations.*'], 'children' => [
                ['label' => 'quotations', 'icon' => 'document', 'route' => 'quotations.index', 'permission' => 'quotations.view'],
                ['label' => 'new_quotation', 'icon' => 'document', 'route' => 'quotations.create', 'permission' => 'quotations.create'],
            ]],
        ],
    ],
    [
        'label' => 'sales_finance',
        'items' => [
            ['label' => 'invoices', 'icon' => 'invoice', 'active_routes' => ['invoices.*'], 'children' => [
                ['label' => 'all_invoices', 'icon' => 'invoice', 'route' => 'invoices.index', 'inactive_when_query' => ['status', 'payment_status'], 'permission' => 'invoices.view'],
                ['label' => 'draft_invoices', 'icon' => 'document', 'route' => 'invoices.index', 'route_parameters' => ['status' => 'draft'], 'permission' => 'invoices.view'],
                ['label' => 'issued_invoices', 'icon' => 'document', 'route' => 'invoices.index', 'route_parameters' => ['status' => 'finalized'], 'permission' => 'invoices.view'],
                ['label' => 'outstanding_invoices', 'icon' => 'wallet', 'route' => 'invoices.index', 'route_parameters' => ['payment_status' => 'outstanding'], 'permission' => 'invoices.view'],
                ['label' => 'create_invoice', 'icon' => 'invoice', 'route' => 'invoices.create', 'permission' => 'invoices.create'],
            ]],
            ['label' => 'payments', 'icon' => 'wallet', 'active_routes' => ['payments.*', 'credit-invoices.*'], 'children' => [
                ['label' => 'payments', 'icon' => 'wallet', 'route' => 'payments.index', 'permission' => 'payments.view'],
                ['label' => 'record_payment', 'icon' => 'wallet', 'route' => 'payments.create', 'permission' => 'payments.receive'],
                ['label' => 'credit_management', 'icon' => 'credit', 'route' => 'credit-invoices.index', 'permission' => 'credit.view'],
                ['label' => 'cash_summary', 'icon' => 'wallet', 'route' => 'reports.show', 'route_parameters' => ['report' => 'cash-summary'], 'permission' => 'reports.view', 'roles' => ['administrator', 'cashier', 'manager']],
            ]],
            ['label' => 'pos', 'icon' => 'pos', 'active_routes' => ['pos.*'], 'children' => [
                ['label' => 'pos', 'icon' => 'pos', 'route' => 'pos.index', 'permission' => 'pos.view'],
                ['label' => 'pos_sales_history', 'icon' => 'document', 'route' => 'pos.sales.index', 'permission' => 'pos.sales.view'],
                ['label' => 'pos_returns', 'icon' => 'receipt', 'route' => 'pos.returns.index', 'permission' => 'pos.return'],
                ['label' => 'pos_reports', 'icon' => 'chart', 'route' => 'pos.reports', 'permission' => 'pos.reports.view'],
            ]],
        ],
    ],
    [
        'label' => 'stock_purchasing',
        'items' => [
            ['label' => 'inventory', 'icon' => 'box', 'active_routes' => ['inventory.*', 'stock-issues.*'], 'children' => [
                ['label' => 'inventory', 'icon' => 'box', 'route' => 'inventory.index', 'permission' => 'inventory.view'],
                ['label' => 'register_part', 'icon' => 'box', 'route' => 'inventory.create', 'permission' => 'inventory.create'],
                ['label' => 'stock_issues', 'icon' => 'box', 'route' => 'stock-issues.index', 'permission' => 'inventory.issue'],
                ['label' => 'stock_issues', 'icon' => 'box', 'route' => 'stock-issues.index', 'permission' => 'jobs.repair', 'roles' => ['technician']],
            ]],
            ['label' => 'suppliers', 'icon' => 'truck', 'active_routes' => ['suppliers.*'], 'children' => [
                ['label' => 'suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'permission' => 'suppliers.view'],
                ['label' => 'register_supplier', 'icon' => 'truck', 'route' => 'suppliers.create', 'permission' => 'suppliers.create'],
            ]],
            ['label' => 'purchases', 'icon' => 'bag', 'active_routes' => ['purchases.*', 'grns.*'], 'children' => [
                ['label' => 'purchases', 'icon' => 'bag', 'route' => 'purchases.index', 'permission' => 'purchases.view'],
                ['label' => 'new_purchase', 'icon' => 'bag', 'route' => 'purchases.create', 'permission' => 'purchases.create'],
            ]],
        ],
    ],
    [
        'label' => 'business_reports',
        'items' => [
            ['label' => 'reports', 'icon' => 'chart', 'active_routes' => ['reports.*'], 'children' => [
                ['label' => 'reports', 'icon' => 'chart', 'route' => 'reports.index', 'permission' => 'reports.view'],
                ['label' => 'repair_reports', 'icon' => 'chart', 'route' => 'reports.show', 'route_parameters' => ['report' => 'repair'], 'permission' => 'reports.repair'],
                ['label' => 'sales_reports', 'icon' => 'chart', 'route' => 'reports.show', 'route_parameters' => ['report' => 'sales'], 'permission' => 'reports.financial'],
                ['label' => 'financial_reports', 'icon' => 'chart', 'route' => 'reports.show', 'route_parameters' => ['report' => 'collections'], 'permission' => 'reports.financial'],
                ['label' => 'credit_reports', 'icon' => 'credit', 'route' => 'reports.show', 'route_parameters' => ['report' => 'customer-credit'], 'permission' => 'reports.credit'],
                ['label' => 'inventory_reports', 'icon' => 'box', 'route' => 'reports.show', 'route_parameters' => ['report' => 'stock'], 'permission' => 'reports.inventory'],
                ['label' => 'supplier_reports', 'icon' => 'truck', 'route' => 'reports.show', 'route_parameters' => ['report' => 'supplier-outstanding'], 'permission' => 'reports.financial'],
                ['label' => 'profit_estimate', 'icon' => 'chart', 'route' => 'reports.show', 'route_parameters' => ['report' => 'profit-estimate'], 'permission' => 'reports.financial'],
            ]],
        ],
    ],
    [
        'label' => 'administration',
        'items' => [
            ['label' => 'employees', 'icon' => 'badge', 'active_routes' => ['employees.*', 'admin.users.*'], 'children' => [
                ['label' => 'employees', 'icon' => 'badge', 'route' => 'employees.index', 'permission' => 'employees.view'],
                ['label' => 'register_employee', 'icon' => 'badge', 'route' => 'employees.create', 'permission' => 'employees.create'],
                ['label' => 'users', 'icon' => 'users', 'route' => 'admin.users.index', 'permission' => 'users.view'],
            ]],
            ['label' => 'roles_permissions', 'icon' => 'shield', 'active_routes' => ['admin.roles.*', 'admin.permissions.*'], 'children' => [
                ['label' => 'roles_permissions', 'icon' => 'shield', 'route' => 'admin.roles.index', 'permission' => 'roles.view'],
                ['label' => 'permissions', 'icon' => 'lock', 'route' => 'admin.permissions.index', 'permission' => 'permissions.manage'],
            ]],
            ['label' => 'system_settings', 'icon' => 'settings', 'active_routes' => ['settings.*', 'print-templates.*'], 'children' => [
                ['label' => 'system_settings', 'icon' => 'settings', 'route' => 'settings.index', 'permission' => 'settings.view'],
                ['label' => 'print_templates', 'icon' => 'document', 'route' => 'print-templates.index', 'permission' => 'print-templates.view'],
            ]],
            ['label' => 'backup_restore', 'icon' => 'database', 'route' => 'backups.index', 'permission' => 'backups.view'],
            ['label' => 'audit_logs', 'icon' => 'activity', 'route' => 'audit-logs.index', 'permission' => 'audit.view'],
            ['label' => 'approvals', 'icon' => 'shield', 'route' => 'approvals.index', 'roles' => ['administrator', 'manager']],
        ],
    ],
];
