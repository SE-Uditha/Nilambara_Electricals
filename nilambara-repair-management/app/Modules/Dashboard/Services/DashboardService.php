<?php

namespace App\Modules\Dashboard\Services;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Reports\Services\ReportDateRange;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly ReportDateRange $dateRanges,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function data(array $filters, User $actor): array
    {
        $range = $this->dateRanges->resolve($filters);
        $periodStart = $range['start']->format('Y-m-d H:i:s');
        $periodEnd = $range['end']->format('Y-m-d H:i:s');
        $terminal = [JobStatus::Delivered->value, JobStatus::Cancelled->value, JobStatus::UnableToRepair->value];

        $jobs = DB::table('repair_jobs');
        if ($actor->hasRole('technician')) {
            $employeeId = DB::table('employees')->where('user_id', $actor->getKey())->value('id');
            if ($employeeId === null) {
                $jobs->whereRaw('1 = 0');
            } else {
                $jobs->where('technician_id', $employeeId);
            }
        }
        $jobCounts = (clone $jobs)
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw('SUM(CASE WHEN status NOT IN (?, ?, ?) THEN 1 ELSE 0 END) as active_jobs', $terminal)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_inspection', [JobStatus::PendingInspection->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_repair', [JobStatus::UnderRepair->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as waiting_for_parts', [JobStatus::WaitingForSpareParts->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_jobs', [JobStatus::RepairCompleted->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ready_for_collection', [JobStatus::ReadyForCollection->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_jobs', [JobStatus::Delivered->value])
            ->selectRaw('SUM(CASE WHEN received_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as received_jobs', [$periodStart, $periodEnd])
            ->first();

        $invoices = DB::table('invoices')
            ->where('status', DocumentStatus::Finalized->value)
            ->whereBetween('finalized_at', [$periodStart, $periodEnd]);
        $posSales = DB::table('pos_sales')
            ->where('status', DocumentStatus::Finalized->value)
            ->whereBetween('finalized_at', [$periodStart, $periodEnd]);
        $paymentReversals = DB::table('payment_reversals')
            ->select('payment_id')
            ->selectRaw('SUM(amount) as reversed_amount')
            ->groupBy('payment_id');
        $payments = DB::table('payments')
            ->leftJoinSub($paymentReversals, 'payment_reversals', 'payment_reversals.payment_id', '=', 'payments.id')
            ->whereBetween('payments.paid_at', [$periodStart, $periodEnd])
            ->select('payments.*')
            ->selectRaw('payments.amount - COALESCE(payment_reversals.reversed_amount, 0) as net_amount');

        $invoiceSales = (clone $invoices)->sum('total');
        $posRevenue = (clone $posSales)->sum('total');
        $invoiceCashSales = (clone $invoices)->where('payment_terms', 'cash')->sum('total');
        $posCashSales = (clone $posSales)->where('payment_method', 'cash')->sum('total');
        $creditSales = (clone $invoices)->where('payment_terms', 'credit')->sum('total');
        $inventory = $this->inventory->defaultLocationBalancesQuery()->where('spare_parts.is_active', true);
        $balance = 'COALESCE(stock_balances.available_quantity, 0)';
        $inventoryCounts = (clone $inventory)
            ->selectRaw('COUNT(DISTINCT spare_parts.id) as part_count')
            ->selectRaw("SUM(CASE WHEN {$balance} <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->selectRaw("SUM(CASE WHEN {$balance} > 0 AND {$balance} <= CASE WHEN spare_parts.minimum_stock_level > 0 THEN spare_parts.minimum_stock_level ELSE spare_parts.reorder_level END THEN 1 ELSE 0 END) as low_stock")
            ->first();
        $creditBalance = DB::table('customer_account_entries')->sum('amount');
        $cashPayments = (clone $payments)->where('payments.method', 'cash')->sum('net_amount');
        $advancePayments = (clone $payments)->where('payments.payment_type', 'advance')->sum('net_amount');
        $creditCollections = (clone $payments)->where('payments.payment_type', 'credit_collection')->sum('net_amount');

        return [
            'range' => $range,
            'jobs' => (array) $jobCounts,
            'sales' => [
                'repair' => $this->money($invoiceSales),
                'pos' => $this->money($posRevenue),
                'total' => $this->money(BigDecimal::of((string) $invoiceSales)->plus((string) $posRevenue)),
                'cash_terms' => $this->money(BigDecimal::of((string) $invoiceCashSales)->plus((string) $posCashSales)),
                'credit_terms' => $this->money($creditSales),
            ],
            'payments' => [
                'collected' => $this->money((clone $payments)->sum('net_amount')),
                'cash' => $this->money($cashPayments),
                'advances' => $this->money($advancePayments),
                'credit_collections' => $this->money($creditCollections),
                'customer_credit' => $this->money(BigDecimal::of((string) $creditBalance)->isGreaterThan(0)
                    ? BigDecimal::of((string) $creditBalance)
                    : '0.00'),
            ],
            'inventory' => [
                'parts' => (int) ($inventoryCounts->part_count ?? 0),
                'low_stock' => (int) ($inventoryCounts->low_stock ?? 0),
                'out_of_stock' => (int) ($inventoryCounts->out_of_stock ?? 0),
            ],
            'recent_jobs' => $this->recentJobs($actor),
            'recent_invoices' => DB::table('invoices')->join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->select('invoices.id', 'invoices.invoice_number', 'invoices.invoice_date', 'invoices.total', 'invoices.status', 'customers.customer_name')
                ->where('invoices.status', DocumentStatus::Finalized->value)->orderByDesc('invoices.finalized_at')->limit(6)->get(),
            'recent_pos_sales' => DB::table('pos_sales')->select('id', 'sale_number', 'sale_at', 'total', 'payment_status')
                ->where('status', DocumentStatus::Finalized->value)->orderByDesc('sale_at')->limit(6)->get(),
            'low_stock_items' => $this->inventory->defaultLocationBalancesQuery()
                ->leftJoin('spare_part_categories', 'spare_parts.category_id', '=', 'spare_part_categories.id')
                ->select('spare_parts.id', 'spare_parts.sku', 'spare_parts.name', 'spare_parts.minimum_stock_level', 'spare_parts.reorder_level', 'available_quantity', 'spare_part_categories.name as category_name')
                ->where('spare_parts.is_active', true)
                ->whereRaw("{$balance} <= CASE WHEN spare_parts.minimum_stock_level > 0 THEN spare_parts.minimum_stock_level ELSE spare_parts.reorder_level END")
                ->orderByRaw($balance)->limit(8)->get(),
        ];
    }

    private function money(mixed $amount): string
    {
        return (string) BigDecimal::of((string) $amount)->toScale(2);
    }

    private function recentJobs(User $actor): Collection
    {
        $query = DB::table('repair_jobs')->join('customers', 'repair_jobs.customer_id', '=', 'customers.id')
            ->select('repair_jobs.id', 'repair_jobs.job_number', 'repair_jobs.item_name', 'repair_jobs.status', 'repair_jobs.received_at', 'customers.customer_name');

        if ($actor->hasRole('technician')) {
            $employeeId = DB::table('employees')->where('user_id', $actor->getKey())->value('id');
            if ($employeeId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('repair_jobs.technician_id', $employeeId);
            }
        }

        return $query->orderByDesc('repair_jobs.received_at')->limit(8)->get();
    }
}
