<?php

namespace App\Modules\Reports\Services;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportDataService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InvoiceBalanceService $invoiceBalances,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, columns: array<int, array{field: string, label: string, type?: string}>, query: Builder, summary: array<string, string|int>, warnings: array<int, string>}
     */
    public function build(string $report, array $filters, User $actor): array
    {
        if ($actor->hasRole('cashier') && ! $actor->can('reports.financial')) {
            $filters['employee_id'] = $this->employeeId($actor) ?: -1;
        }
        if (in_array($report, ['daily-sales', 'monthly-sales', 'yearly-sales', 'repair-income', 'invoice-sales', 'cash-credit-sales', 'cashier-sales', 'discounts'], true)) {
            $filters['sales_report'] = $report;

            return $this->sales($filters);
        }
        if (in_array($report, ['customer-statement', 'supplier-statement', 'refunds'], true)) {
            return $this->ledgerReport($report, $filters);
        }
        if (in_array($report, ['cash-summary', 'cash-collections'], true)) {
            $filters['method'] = 'cash';

            return $this->financial('collections', $filters);
        }

        return match ($report) {
            'repair' => $this->repair($filters, $actor),
            'technicians' => $this->technicians($filters, $actor),
            'sales' => $this->sales($filters),
            'collections', 'advances', 'customer-credit', 'overdue-credit', 'supplier-outstanding', 'purchases', 'expenses' => $this->financial($report, $filters),
            'stock', 'stock-movements', 'repair-parts', 'pos-parts', 'adjustments', 'purchase-history', 'supplier-purchases', 'valuation' => $this->inventoryReport($report, $filters),
            'profit-estimate' => $this->profitEstimate($filters),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function repair(array $filters, User $actor): array
    {
        $query = DB::table('repair_jobs')
            ->join('customers', 'repair_jobs.customer_id', '=', 'customers.id')
            ->leftJoin('employees as technician', 'repair_jobs.technician_id', '=', 'technician.id')
            ->select([
                'repair_jobs.job_number',
                'repair_jobs.received_at',
                'customers.customer_name',
                'repair_jobs.item_name',
                'technician.name as technician_name',
                'repair_jobs.status',
                'repair_jobs.completed_at',
                'repair_jobs.total_job_amount',
                'repair_jobs.customer_id',
                'repair_jobs.technician_id',
            ]);
        $hours = DB::getDriverName() === 'sqlite'
            ? '(julianday(repair_jobs.completed_at) - julianday(repair_jobs.received_at)) * 24'
            : 'TIMESTAMPDIFF(SECOND, repair_jobs.received_at, repair_jobs.completed_at) / 3600';
        $query->selectRaw("{$hours} as completion_hours");
        $this->dateFilters($query, 'repair_jobs.received_at', $filters);
        $query->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('repair_jobs.status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, int $customerId) => $builder->where('repair_jobs.customer_id', $customerId))
            ->when($filters['employee_id'] ?? null, fn (Builder $builder, int $employeeId) => $builder->where('repair_jobs.technician_id', $employeeId))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $term) => $builder->where(function (Builder $search) use ($term): void {
                $search->where('repair_jobs.job_number', 'like', '%'.$term.'%')
                    ->orWhere('repair_jobs.item_name', 'like', '%'.$term.'%')
                    ->orWhere('customers.customer_name', 'like', '%'.$term.'%');
            }));

        if ($actor->hasRole('technician')) {
            $employeeId = $this->employeeId($actor);
            $query->where('repair_jobs.technician_id', $employeeId > 0 ? $employeeId : -1);
        }

        return [
            'title' => __('reports.repair_title'),
            'columns' => $this->columns([
                'job_number' => 'job_number', 'received_at' => 'received', 'customer_name' => 'customer',
                'item_name' => 'item', 'technician_name' => 'technician', 'status' => 'status',
                'completed_at' => 'completed', 'total_job_amount' => 'repair_amount',
                'completion_hours' => 'average_completion_hours',
            ]),
            'query' => $query->orderByDesc('repair_jobs.received_at'),
            'summary' => ['rows' => (string) $this->countRows($query)],
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function technicians(array $filters, User $actor): array
    {
        $employeeId = $actor->hasRole('technician') ? $this->employeeId($actor) : ($filters['employee_id'] ?? null);
        $driver = DB::connection()->getDriverName();
        $completionHours = $driver === 'sqlite'
            ? '(julianday(repair_jobs.completed_at) - julianday(repair_jobs.received_at)) * 24'
            : '(TIMESTAMPDIFF(SECOND, repair_jobs.received_at, repair_jobs.completed_at) / 3600)';
        $query = DB::table('employees')
            ->leftJoin('repair_jobs', 'repair_jobs.technician_id', '=', 'employees.id')
            ->where(function (Builder $builder): void {
                $builder->whereNotNull('repair_jobs.id')->orWhere('employees.job_title', 'like', '%technician%');
            })
            ->when($employeeId !== null, fn (Builder $builder) => $builder->where('employees.id', $employeeId))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('repair_jobs.received_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('repair_jobs.received_at', '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('repair_jobs.status', $status))
            ->select('employees.id', 'employees.name')
            ->selectRaw('COUNT(DISTINCT repair_jobs.id) as assigned_jobs')
            ->selectRaw('COUNT(DISTINCT CASE WHEN repair_jobs.completed_at IS NOT NULL THEN repair_jobs.id END) as completed_jobs')
            ->selectRaw("COUNT(DISTINCT CASE WHEN repair_jobs.id IS NOT NULL AND repair_jobs.completed_at IS NULL AND repair_jobs.status NOT IN ('Cancelled', 'Unable to Repair') THEN repair_jobs.id END) as pending_jobs")
            ->selectRaw("AVG(CASE WHEN repair_jobs.completed_at IS NOT NULL THEN {$completionHours} END) as average_completion_hours")
            ->selectRaw("SUM(CASE WHEN repair_jobs.status = 'Pending Inspection' THEN 1 ELSE 0 END) as pending_inspection")
            ->selectRaw("SUM(CASE WHEN repair_jobs.status = 'Under Repair' THEN 1 ELSE 0 END) as under_repair")
            ->selectRaw("SUM(CASE WHEN repair_jobs.status = 'Waiting for Spare Parts' THEN 1 ELSE 0 END) as waiting_for_parts")
            ->groupBy('employees.id', 'employees.name');

        return [
            'title' => __('reports.technicians_title'),
            'columns' => $this->columns([
                'name' => 'technician', 'assigned_jobs' => 'assigned_jobs', 'completed_jobs' => 'completed_jobs',
                'pending_jobs' => 'pending_jobs', 'average_completion_hours' => 'average_completion_hours',
                'pending_inspection' => 'pending_inspection', 'under_repair' => 'under_repair', 'waiting_for_parts' => 'waiting_for_parts',
            ]),
            'query' => $query->orderByDesc('assigned_jobs'),
            'summary' => ['rows' => (string) $this->countRows($query)],
            'warnings' => [__('reports.technician_attribution_current')],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function sales(array $filters): array
    {
        $invoices = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->join('employees', 'invoices.cashier_employee_id', '=', 'employees.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->selectRaw("'Repair invoice' as document_type, invoices.invoice_number as document_number, invoices.invoice_date as transaction_date, customers.customer_name, employees.name as employee_name, invoices.payment_terms, invoices.payment_method, invoices.subtotal, invoices.discount_amount, invoices.total as net_sales, invoices.customer_id, invoices.cashier_employee_id as employee_id");
        $pos = DB::table('pos_sales')
            ->leftJoin('customers', 'pos_sales.customer_id', '=', 'customers.id')
            ->join('employees', 'pos_sales.cashier_employee_id', '=', 'employees.id')
            ->where('pos_sales.status', DocumentStatus::Finalized->value)
            ->selectRaw("'POS sale' as document_type, pos_sales.sale_number as document_number, pos_sales.sale_at as transaction_date, COALESCE(customers.customer_name, 'Walk-in') as customer_name, employees.name as employee_name, pos_sales.payment_terms, pos_sales.payment_method, pos_sales.subtotal, pos_sales.discount_amount, pos_sales.total as net_sales, pos_sales.customer_id, pos_sales.cashier_employee_id as employee_id");
        $refunds = DB::table('payment_reversals')->join('payments', 'payment_reversals.payment_id', '=', 'payments.id')
            ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
            ->join('employees', 'payments.received_by_employee_id', '=', 'employees.id')
            ->whereIn('payments.payment_type', ['pos', 'invoice', 'credit_collection'])
            ->selectRaw("'Refund' as document_type, payments.payment_number as document_number, payment_reversals.reversed_at as transaction_date, COALESCE(customers.customer_name, 'Walk-in') as customer_name, employees.name as employee_name, CASE WHEN payments.payment_type = 'credit_collection' THEN 'credit' ELSE 'cash' END as payment_terms, payment_reversals.method as payment_method, 0 as subtotal, 0 as discount_amount, -payment_reversals.amount as net_sales, payments.customer_id, payments.received_by_employee_id as employee_id");
        $union = $invoices->unionAll($pos)->unionAll($refunds);
        $query = DB::query()->fromSub($union, 'sales')
            ->select('document_type', 'document_number', 'transaction_date', 'customer_name', 'employee_name', 'payment_terms', 'payment_method', 'subtotal', 'discount_amount', 'net_sales', 'customer_id', 'employee_id');
        $this->dateFilters($query, 'sales.transaction_date', $filters);
        $query->when($filters['customer_id'] ?? null, fn (Builder $builder, int $customerId) => $builder->where('sales.customer_id', $customerId))
            ->when($filters['employee_id'] ?? null, fn (Builder $builder, int $employeeId) => $builder->where('sales.employee_id', $employeeId))
            ->when($filters['method'] ?? null, fn (Builder $builder, string $method) => $builder->where('sales.payment_method', $method))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $term) => $builder->where('sales.document_number', 'like', '%'.$term.'%'));

        $variant = $filters['sales_report'] ?? 'sales';
        if (in_array($variant, ['repair-income', 'invoice-sales'], true)) {
            $query->where('sales.document_type', 'Repair invoice');
        }
        if ($variant === 'discounts') {
            $query->where('sales.discount_amount', '>', 0);
        }
        if (isset($filters['payment_terms'])) {
            $query->where('sales.payment_terms', $filters['payment_terms']);
        }
        if (in_array($variant, ['daily-sales', 'monthly-sales', 'yearly-sales', 'cash-credit-sales', 'cashier-sales'], true)) {
            $period = match ($variant) {
                'monthly-sales' => '%Y-%m', 'yearly-sales' => '%Y', default => '%Y-%m-%d'
            };
            $group = match ($variant) {
                'cash-credit-sales' => 'payment_terms', 'cashier-sales' => 'employee_id',
                default => DB::getDriverName() === 'sqlite' ? "strftime('{$period}', transaction_date)" : "DATE_FORMAT(transaction_date, '{$period}')",
            };
            $label = $variant === 'cashier-sales' ? 'MAX(employee_name)' : $group;
            $query->select([])->selectRaw("{$label} as period, COUNT(*) as documents, SUM(subtotal) as subtotal, SUM(discount_amount) as discount_amount, SUM(net_sales) as net_sales")
                ->groupByRaw($group);

            return [
                'title' => __('reports.'.$variant.'_title'),
                'columns' => $this->columns(['period' => 'period', 'documents' => 'rows', 'subtotal' => 'gross_sales', 'discount_amount' => 'discounts', 'net_sales' => 'net_sales']),
                'query' => $query->orderBy('period'), 'summary' => [],
                'warnings' => [__('reports.sales_not_collections'), 'Cancelled documents are excluded. Refunds reduce net sales on their reversal date.'],
            ];
        }

        return [
            'title' => __('reports.'.$variant.'_title'),
            'columns' => $this->columns([
                'document_type' => 'document_type', 'document_number' => 'document_number', 'transaction_date' => 'date',
                'customer_name' => 'customer', 'employee_name' => 'cashier', 'payment_terms' => 'terms',
                'payment_method' => 'method', 'subtotal' => 'gross_sales', 'discount_amount' => 'discounts', 'net_sales' => 'net_sales',
            ]),
            'query' => $query->orderByDesc('sales.transaction_date'),
            'summary' => [
                'gross_sales' => $this->money((clone $query)->sum('subtotal')),
                'discounts' => $this->money((clone $query)->sum('discount_amount')),
                'net_sales' => $this->money((clone $query)->sum('net_sales')),
            ],
            'warnings' => [__('reports.sales_not_collections'), 'Refund rows reduce net sales on reversal dates. Repair income and invoice-only views show original issued invoice amounts; use the refund report for adjustments. Refund cash/credit attribution uses original collection type; mixed-source allocation attribution requires an approved accounting rule.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function financial(string $report, array $filters): array
    {
        $warnings = [];
        $summary = [];
        $columns = [];
        $query = match ($report) {
            'collections', 'advances' => $this->paymentQuery($report === 'advances', $filters),
            'customer-credit' => $this->customerCreditQuery($filters),
            'overdue-credit' => $this->overdueInvoicesQuery($filters),
            'supplier-outstanding' => $this->supplierOutstandingQuery($filters),
            'purchases', 'supplier-purchases' => $this->purchaseQuery($filters),
            'expenses' => $this->expenseQuery($filters),
        };
        $columns = match ($report) {
            'collections', 'advances' => $this->columns([
                'payment_number' => 'payment_number', 'paid_at' => 'date', 'customer_name' => 'customer',
                'payment_type' => 'payment_type', 'method' => 'method', 'amount' => 'amount',
                'received_by' => 'received_by', 'net_amount' => 'net_collected',
            ]),
            'customer-credit' => $this->columns(['customer_name' => 'customer', 'mobile_number' => 'mobile', 'outstanding' => 'outstanding']),
            'overdue-credit' => $this->columns(['invoice_number' => 'invoice_number', 'invoice_date' => 'date', 'due_date' => 'due_date', 'customer_name' => 'customer', 'total' => 'invoice_total', 'outstanding' => 'outstanding']),
            'supplier-outstanding' => $this->columns(['supplier_name' => 'supplier', 'mobile_number' => 'mobile', 'outstanding' => 'outstanding']),
            'purchases', 'supplier-purchases' => $this->columns(['purchase_number' => 'purchase_number', 'purchase_date' => 'date', 'supplier_name' => 'supplier', 'payment_terms' => 'terms', 'total' => 'amount']),
            'expenses' => $this->columns(['expense_number' => 'expense_number', 'incurred_at' => 'date', 'category_name' => 'category', 'description' => 'description', 'method' => 'method', 'amount' => 'amount']),
        };
        if ($report === 'collections' || $report === 'advances') {
            $summary['total'] = $this->money(DB::query()->fromSub(clone $query, 'report_rows')->sum('net_amount'));
            $warnings[] = __('reports.net_collections');
        } elseif ($report === 'customer-credit') {
            $summary['total'] = $this->money(DB::query()->fromSub(clone $query, 'report_rows')->sum('outstanding'));
        } elseif ($report === 'supplier-outstanding') {
            $summary['total'] = $this->money(DB::query()->fromSub(clone $query, 'report_rows')->sum('outstanding'));
        } elseif ($report === 'purchases' || $report === 'supplier-purchases' || $report === 'expenses') {
            $summary['total'] = $this->money((clone $query)->sum($report === 'expenses' ? 'amount' : 'total'));
        } elseif ($report === 'overdue-credit') {
            $warnings[] = __('reports.overdue_policy_due_date');
        }

        return [
            'title' => __('reports.'.$report.'_title'),
            'columns' => $columns,
            'query' => $query,
            'summary' => $summary + ['rows' => (string) $this->countRows($query)],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentQuery(bool $advances, array $filters): Builder
    {
        $receipts = DB::table('payments')
            ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
            ->join('employees', 'payments.received_by_employee_id', '=', 'employees.id')
            ->select('payments.payment_number', 'payments.paid_at', 'customers.customer_name', 'payments.payment_type', 'payments.method', 'payments.amount', 'employees.name as received_by')
            ->selectRaw('payments.amount as net_amount')
            ->when($advances, fn (Builder $builder) => $builder->where('payments.payment_type', 'advance'));
        $refunds = DB::table('payment_reversals')->join('payments', 'payment_reversals.payment_id', '=', 'payments.id')
            ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
            ->join('employees', 'payments.received_by_employee_id', '=', 'employees.id')
            ->select('payments.payment_number', 'payment_reversals.reversed_at as paid_at', 'customers.customer_name', 'payments.payment_type', 'payment_reversals.method', 'payment_reversals.amount', 'employees.name as received_by')
            ->selectRaw('-payment_reversals.amount as net_amount')
            ->when($advances, fn (Builder $builder) => $builder->where('payments.payment_type', 'advance'));
        foreach ([$receipts, $refunds] as $entryQuery) {
            $entryQuery->when($filters['customer_id'] ?? null, fn ($builder, $id) => $builder->where('payments.customer_id', $id))
                ->when($filters['employee_id'] ?? null, fn ($builder, $id) => $builder->where('payments.received_by_employee_id', $id));
        }
        $query = DB::query()->fromSub($receipts->unionAll($refunds), 'collections')->select('collections.*');
        $this->dateFilters($query, 'paid_at', $filters);

        return $query
            ->when($filters['method'] ?? null, fn (Builder $builder, string $method) => $builder->where('method', $method))
            ->orderByDesc('paid_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function customerCreditQuery(array $filters): Builder
    {
        return DB::table('customer_account_entries')
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('customer_account_entries.occurred_at', '<=', $date))
            ->join('customers', 'customer_account_entries.customer_id', '=', 'customers.id')
            ->select('customers.customer_name', 'customers.mobile_number')
            ->selectRaw('SUM(customer_account_entries.amount) as outstanding')
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('customers.id', $id))
            ->groupBy('customers.id', 'customers.customer_name', 'customers.mobile_number')
            ->havingRaw('SUM(customer_account_entries.amount) > 0')
            ->orderByDesc('outstanding');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function overdueInvoicesQuery(array $filters): Builder
    {
        $query = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->whereNotNull('invoices.due_date')
            ->whereDate('invoices.due_date', '<', now(config('business.timezone', 'Asia/Colombo'))->toDateString())
            ->select('invoices.invoice_number', 'invoices.invoice_date', 'invoices.due_date', 'customers.customer_name', 'invoices.total', 'invoices.id');
        $this->dateFilters($query, 'invoices.invoice_date', $filters);

        $paid = $this->invoiceBalances->netPaidExpression();
        $query->selectRaw("CASE WHEN invoices.total > {$paid} THEN invoices.total - {$paid} ELSE 0 END as outstanding");

        return $this->invoiceBalances->constrainPaymentStatus($query, 'outstanding')
            ->when($filters['customer_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('invoices.customer_id', $id))
            ->orderBy('invoices.due_date');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function supplierOutstandingQuery(array $filters): Builder
    {
        return DB::table('supplier_account_entries')
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('supplier_account_entries.occurred_at', '<=', $date))
            ->join('suppliers', 'supplier_account_entries.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.name as supplier_name', 'suppliers.mobile_number')
            ->selectRaw('SUM(supplier_account_entries.amount) as outstanding')
            ->when($filters['supplier_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('suppliers.id', $id))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.mobile_number')
            ->havingRaw('SUM(supplier_account_entries.amount) > 0')
            ->orderByDesc('outstanding');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function purchaseQuery(array $filters): Builder
    {
        $query = DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->where('purchases.status', DocumentStatus::Finalized->value)
            ->select('purchases.purchase_number', 'purchases.purchase_date', 'suppliers.name as supplier_name', 'purchases.payment_terms', 'purchases.total');
        $this->dateFilters($query, 'purchases.purchase_date', $filters);

        return $query->when($filters['supplier_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('purchases.supplier_id', $id))
            ->orderByDesc('purchases.purchase_date');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function expenseQuery(array $filters): Builder
    {
        $query = DB::table('expenses')
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->where('expenses.status', DocumentStatus::Finalized->value)
            ->select('expenses.expense_number', 'expenses.incurred_at', 'expense_categories.name as category_name', 'expenses.description', 'expenses.method', 'expenses.amount')
            ->selectRaw('expenses.amount as total');
        $this->dateFilters($query, 'expenses.incurred_at', $filters);

        return $query->orderByDesc('expenses.incurred_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function inventoryReport(string $report, array $filters): array
    {
        $warnings = [];
        if (in_array($report, ['stock', 'valuation'], true)) {
            $query = $this->inventory->defaultLocationBalancesQuery(isset($filters['date_to']) ? $filters['date_to'].' 23:59:59' : null)
                ->leftJoin('spare_part_categories', 'spare_parts.category_id', '=', 'spare_part_categories.id')
                ->leftJoin('spare_part_brands', 'spare_parts.brand_id', '=', 'spare_part_brands.id')
                ->leftJoin('suppliers', 'spare_parts.supplier_id', '=', 'suppliers.id')
                ->select('spare_parts.id', 'spare_parts.sku', 'spare_parts.name', 'spare_parts.selling_price', 'spare_parts.minimum_stock_level', 'spare_parts.reorder_level', 'spare_part_categories.name as category_name', 'spare_part_brands.name as brand_name', 'suppliers.name as supplier_name', 'available_quantity');
            if ($report === 'valuation') {
                $warnings[] = __('reports.valuation_policy_missing');
            }
            $this->stockFilters($query, $filters);
            if (($filters['stock_state'] ?? null) === 'low') {
                $query->whereRaw('COALESCE(stock_balances.available_quantity, 0) > 0 AND COALESCE(stock_balances.available_quantity, 0) <= CASE WHEN spare_parts.minimum_stock_level > 0 THEN spare_parts.minimum_stock_level ELSE spare_parts.reorder_level END');
            } elseif (($filters['stock_state'] ?? null) === 'out') {
                $query->whereRaw('COALESCE(stock_balances.available_quantity, 0) <= 0');
            }
            $columns = $this->columns([
                'sku' => 'sku', 'name' => 'part', 'category_name' => 'category', 'brand_name' => 'brand',
                'available_quantity' => 'quantity', 'selling_price' => 'selling_price', 'supplier_name' => 'supplier',
            ]);
            if ($report === 'valuation') {
                $columns[] = ['field' => 'costing_note', 'label' => __('reports.costing_not_approved')];
                $query->addSelect(DB::raw("'Not approved' as costing_note"));
            }

            return [
                'title' => __('reports.'.$report.'_title'), 'columns' => $columns,
                'query' => $query->where('spare_parts.is_active', true)->orderBy('spare_parts.sku'),
                'summary' => ['rows' => (string) $this->countRows($query)],
                'warnings' => $warnings,
            ];
        }

        $query = match ($report) {
            'stock-movements' => DB::table('stock_movements')
                ->join('spare_parts', 'stock_movements.spare_part_id', '=', 'spare_parts.id')
                ->join('stock_locations', 'stock_movements.location_id', '=', 'stock_locations.id')
                ->select('stock_movements.occurred_at', 'spare_parts.sku', 'spare_parts.name', 'stock_movements.movement_type', 'stock_movements.quantity_delta', 'stock_movements.unit_cost', 'stock_movements.reference_number', 'stock_locations.name as location_name'),
            'repair-parts' => DB::table('job_part_usages')
                ->join('repair_jobs', 'job_part_usages.repair_job_id', '=', 'repair_jobs.id')
                ->join('spare_parts', 'job_part_usages.spare_part_id', '=', 'spare_parts.id')
                ->where('job_part_usages.status', 'issued')
                ->select('repair_jobs.job_number as reference_number', 'repair_jobs.received_at as occurred_at', 'spare_parts.sku', 'spare_parts.name', 'job_part_usages.quantity', 'job_part_usages.unit_cost', 'repair_jobs.status'),
            'pos-parts' => DB::table('pos_sale_items')
                ->join('pos_sales', 'pos_sale_items.pos_sale_id', '=', 'pos_sales.id')
                ->join('spare_parts', 'pos_sale_items.spare_part_id', '=', 'spare_parts.id')
                ->where('pos_sales.status', DocumentStatus::Finalized->value)
                ->select('pos_sales.sale_number as reference_number', 'pos_sales.sale_at as occurred_at', 'spare_parts.sku', 'spare_parts.name', 'pos_sale_items.quantity', 'pos_sale_items.unit_cost', 'pos_sale_items.line_total'),
            'adjustments' => DB::table('stock_movements')
                ->join('stock_adjustment_items', 'stock_movements.adjustment_item_id', '=', 'stock_adjustment_items.id')
                ->join('stock_adjustments', 'stock_adjustment_items.stock_adjustment_id', '=', 'stock_adjustments.id')
                ->join('spare_parts', 'stock_movements.spare_part_id', '=', 'spare_parts.id')
                ->whereIn('stock_movements.movement_type', ['adjustment', 'damaged', 'count_correction'])
                ->where('stock_adjustments.status', DocumentStatus::Finalized->value)
                ->select('stock_adjustments.adjustment_number as reference_number', 'stock_movements.occurred_at', 'spare_parts.sku', 'spare_parts.name', 'stock_movements.movement_type', 'stock_movements.quantity_delta', 'stock_movements.reason'),
            'purchase-history', 'supplier-purchases' => DB::table('goods_received_note_items')
                ->join('goods_received_notes', 'goods_received_note_items.goods_received_note_id', '=', 'goods_received_notes.id')
                ->join('suppliers', 'goods_received_notes.supplier_id', '=', 'suppliers.id')
                ->join('spare_parts', 'goods_received_note_items.spare_part_id', '=', 'spare_parts.id')
                ->where('goods_received_notes.status', DocumentStatus::Finalized->value)
                ->select('goods_received_notes.grn_number as reference_number', 'goods_received_notes.received_at as occurred_at', 'suppliers.name as supplier_name', 'spare_parts.sku', 'spare_parts.name', 'goods_received_note_items.quantity', 'goods_received_note_items.unit_cost'),
        };
        $this->stockFilters($query, $filters);
        $query = DB::query()->fromSub($query, 'movement_rows');
        $dateColumn = 'occurred_at';
        $this->dateFilters($query, $dateColumn, $filters);

        return [
            'title' => __('reports.'.$report.'_title'),
            'columns' => $this->columns([
                'occurred_at' => 'date', 'reference_number' => 'reference', 'sku' => 'sku',
                'name' => 'part', 'movement_type' => 'movement_type', 'quantity_delta' => 'quantity',
                'quantity' => 'quantity', 'unit_cost' => 'unit_cost', 'line_total' => 'sales_total',
                'location_name' => 'location', 'supplier_name' => 'supplier', 'status' => 'status', 'reason' => 'reason',
            ]),
            'query' => $query->orderByDesc($dateColumn),
            'summary' => ['rows' => (string) $this->countRows($query)],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function profitEstimate(array $filters): array
    {
        $invoices = DB::table('invoices')->where('status', DocumentStatus::Finalized->value);
        $pos = DB::table('pos_sales')->where('status', DocumentStatus::Finalized->value);
        $this->dateFilters($invoices, 'invoices.finalized_at', $filters);
        $this->dateFilters($pos, 'pos_sales.finalized_at', $filters);
        $repairRevenue = $invoices->sum('total');
        $posRevenue = $pos->sum('total');
        $discounts = BigDecimal::of((string) (clone $invoices)->sum('discount_amount'))
            ->plus((string) (clone $pos)->sum('discount_amount'))->toScale(2);
        $refundQuery = DB::table('payment_reversals')->join('payments', 'payment_reversals.payment_id', '=', 'payments.id')
            ->whereIn('payments.payment_type', ['pos', 'invoice', 'credit_collection']);
        $this->dateFilters($refundQuery, 'payment_reversals.reversed_at', $filters);
        $refunds = $refundQuery->sum('payment_reversals.amount');
        $revenue = BigDecimal::of((string) $repairRevenue)->plus((string) $posRevenue)->minus((string) $refunds)->toScale(2);
        $partCost = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->where('invoice_items.item_type', 'part')
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('invoices.finalized_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('invoices.finalized_at', '<=', $date))
            ->whereNotNull('invoice_items.unit_cost')
            ->sum(DB::raw('invoice_items.quantity * invoice_items.unit_cost'));
        $posCost = DB::table('pos_sale_items')->join('pos_sales', 'pos_sale_items.pos_sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.status', DocumentStatus::Finalized->value)->whereNotNull('pos_sale_items.unit_cost');
        $this->dateFilters($posCost, 'pos_sales.finalized_at', $filters);
        $partCost = BigDecimal::of((string) $partCost)->plus((string) $posCost->sum(DB::raw('pos_sale_items.quantity * pos_sale_items.unit_cost')));
        $labourCost = DB::table('repair_jobs')
            ->join('invoice_jobs', 'repair_jobs.id', '=', 'invoice_jobs.repair_job_id')
            ->join('invoices', 'invoice_jobs.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->whereNotNull('repair_jobs.labour_cost')
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('invoices.finalized_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('invoices.finalized_at', '<=', $date))
            ->sum('repair_jobs.labour_cost');
        $expenses = DB::table('expenses')->where('status', DocumentStatus::Finalized->value)
            ->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('incurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('incurred_at', '<=', $date))
            ->sum('amount');
        $missingCostQuery = DB::table('pos_sale_items')->join('pos_sales', 'pos_sale_items.pos_sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.status', DocumentStatus::Finalized->value)
            ->whereNull('pos_sale_items.unit_cost');
        $this->dateFilters($missingCostQuery, 'pos_sales.finalized_at', $filters);
        $missingCost = $missingCostQuery->exists();
        $missingLabourCostQuery = DB::table('repair_jobs')
            ->join('invoice_jobs', 'repair_jobs.id', '=', 'invoice_jobs.repair_job_id')
            ->join('invoices', 'invoice_jobs.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->whereNull('repair_jobs.labour_cost');
        $this->dateFilters($missingLabourCostQuery, 'invoices.finalized_at', $filters);
        $missingCost = $missingCost || $missingLabourCostQuery->exists();
        $missingPartCostQuery = DB::table('invoice_items')->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', DocumentStatus::Finalized->value)
            ->where('invoice_items.item_type', 'part')->whereNull('invoice_items.unit_cost');
        $this->dateFilters($missingPartCostQuery, 'invoices.finalized_at', $filters);
        // No approved stock/expense recognition policy exists yet. Known costs
        // are shown, but must not be represented as a complete net profit.
        $missingCost = true;
        $summary = [
            'repair_revenue' => $this->money($repairRevenue),
            'pos_revenue' => $this->money($posRevenue),
            'net_revenue' => $this->money($revenue),
            'parts_cost' => $this->money($partCost),
            'labour_cost' => $this->money($labourCost),
            'discounts' => $this->money($discounts),
            'refunds' => $this->money($refunds),
            'expenses' => $this->money($expenses),
            'estimated_profit' => $missingCost ? __('reports.incomplete') : $this->money($revenue->minus((string) $partCost)->minus((string) $labourCost)->minus((string) $expenses)->toScale(2)),
        ];
        $query = DB::query()->selectRaw('? as estimated_profit, ? as net_revenue', [$summary['estimated_profit'], $summary['net_revenue']]);

        return [
            'title' => __('reports.profit_title'),
            'columns' => $this->columns(['estimated_profit' => 'estimated_profit', 'net_revenue' => 'net_revenue']),
            'query' => $query,
            'summary' => $summary,
            'warnings' => [$missingCost ? __('reports.profit_costing_incomplete') : __('reports.profit_estimate_only')],
        ];
    }

    private function ledgerReport(string $report, array $filters): array
    {
        if ($report === 'refunds') {
            $query = DB::table('payment_reversals')->join('payments', 'payment_reversals.payment_id', '=', 'payments.id')
                ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
                ->select('payment_reversals.id', 'payment_reversals.reversed_at as occurred_at', 'payments.payment_number as reference_number', 'customers.customer_name as account_name', 'payment_reversals.amount', 'payment_reversals.method', 'payment_reversals.reason as description')
                ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('payments.customer_id', $id))
                ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('payments.received_by_employee_id', $id))
                ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('payment_reversals.method', $method));
            $query = DB::query()->fromSub($query, 'refund_rows');
        } else {
            $supplier = $report === 'supplier-statement';
            $table = $supplier ? 'supplier_account_entries' : 'customer_account_entries';
            $account = $supplier ? 'suppliers' : 'customers';
            $owner = $supplier ? 'supplier_id' : 'customer_id';
            $name = $supplier ? 'name' : 'customer_name';
            $ledger = DB::table($table)->join($account, $table.'.'.$owner, '=', $account.'.id')
                ->select($table.'.id', $table.'.occurred_at', $table.'.reference_number', $account.'.'.$name.' as account_name', $table.'.amount', $table.'.description')
                ->selectRaw("SUM({$table}.amount) OVER (PARTITION BY {$table}.{$owner} ORDER BY {$table}.occurred_at, {$table}.id ROWS UNBOUNDED PRECEDING) as running_balance")
                ->when($filters[$owner] ?? null, fn ($q, $id) => $q->where($table.'.'.$owner, $id));
            // Window balances include entries before the display's start date.
            $query = DB::query()->fromSub($ledger, 'statement');
        }
        $this->dateFilters($query, 'occurred_at', $filters);
        $query->when($filters['q'] ?? null, fn ($q, $term) => $q->where('reference_number', 'like', '%'.$term.'%'));
        $fields = ['occurred_at' => 'date', 'reference_number' => 'reference', 'account_name' => 'customer', 'description' => 'description', 'amount' => 'amount'];
        $fields += $report === 'refunds' ? ['method' => 'method'] : ['running_balance' => 'outstanding'];

        return ['title' => __('reports.'.$report.'_title'), 'columns' => $this->columns($fields),
            'query' => $query->orderBy('occurred_at')->orderBy('id'),
            'summary' => ['total' => $this->money((clone $query)->sum('amount'))], 'warnings' => []];
    }

    private function stockFilters(Builder $query, array $filters): void
    {
        $query->when($filters['category_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('spare_parts.category_id', $id))
            ->when($filters['item_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('spare_parts.id', $id))
            ->when($filters['supplier_id'] ?? null, fn (Builder $builder, int $id) => $builder->where('spare_parts.supplier_id', $id))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $term) => $builder->where(function (Builder $search) use ($term): void {
                $search->where('spare_parts.sku', 'like', '%'.$term.'%')
                    ->orWhere('spare_parts.name', 'like', '%'.$term.'%');
            }));
    }

    private function dateFilters(Builder $query, string $column, array $filters): void
    {
        $query->when($filters['date_from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate($column, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate($column, '<=', $date));
    }

    private function employeeId(User $actor): int
    {
        return (int) DB::table('employees')->where('user_id', $actor->getKey())->value('id');
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<int, array{field: string, label: string}>
     */
    private function columns(array $fields): array
    {
        return collect($fields)->map(fn (string $label, string $field): array => [
            'field' => $field,
            'label' => __('reports.'.$label),
            'type' => preg_match('/(?:_at|_date)$/', $field) ? 'date'
                : (preg_match('/amount|cost|price|total|sales|quantity|outstanding|balance|hours|jobs|documents/', $field) ? 'number' : 'text'),
            'sum' => in_array($field, ['amount', 'total', 'subtotal', 'discount_amount', 'net_sales', 'net_amount', 'outstanding', 'line_total', 'quantity', 'quantity_delta'], true),
        ])->values()->all();
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    private function countRows(Builder $query): int
    {
        return (int) DB::query()->fromSub((clone $query)->reorder(), 'report_rows')->count();
    }
}
