<?php

namespace App\Modules\Customers\Services;

use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use Brick\Math\BigDecimal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    public function __construct(private readonly InvoiceBalanceService $invoiceBalances) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{entries: LengthAwarePaginator|Collection, openingBalance: string, closingBalance: string}
     */
    public function data(Customer $customer, array $filters, bool $paginate): array
    {
        $allEntries = CustomerAccountEntry::query()
            ->where('customer_id', $customer->getKey())
            ->orderBy('occurred_at')
            ->orderBy('id');
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $filtered = (clone $allEntries)
            ->when($dateFrom, fn ($query) => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('occurred_at', '<=', $dateTo));
        $total = $filtered->count();
        $perPage = $paginate ? 30 : max(1, $total);
        $page = $paginate ? LengthAwarePaginator::resolveCurrentPage() : 1;
        $pageRows = $filtered->with('invoice.invoiceJobs')->forPage($page, $perPage)->get();

        $opening = BigDecimal::zero()->toScale(2);
        if ($pageRows->isNotEmpty()) {
            $first = $pageRows->first();
            $openingRows = (clone $allEntries)
                ->where(function ($query) use ($first): void {
                    $query->where('occurred_at', '<', $first->occurred_at)
                        ->orWhere(function ($sameTime) use ($first): void {
                            $sameTime->where('occurred_at', $first->occurred_at)
                                ->where('id', '<', $first->getKey());
                        });
                })
                ->pluck('amount');
            $opening = $openingRows->reduce(
                fn (BigDecimal $sum, $amount): BigDecimal => $sum->plus((string) $amount),
                $opening,
            );
        } elseif ($dateFrom !== null) {
            $openingRows = (clone $allEntries)->whereDate('occurred_at', '<', $dateFrom)->pluck('amount');
            $opening = $openingRows->reduce(
                fn (BigDecimal $sum, $amount): BigDecimal => $sum->plus((string) $amount),
                $opening,
            );
        }

        $runningBalance = $opening;
        foreach ($pageRows as $entry) {
            $runningBalance = $runningBalance->plus((string) $entry->amount)->toScale(2);
            $entry->setAttribute('running_balance', (string) $runningBalance);
            $entry->setAttribute('advance_applied', $entry->invoice
                ? $this->invoiceBalances->summary($entry->invoice)['advance_paid']
                : '0.00');
        }

        $closing = (clone $allEntries)
            ->when($dateTo, fn ($query) => $query->whereDate('occurred_at', '<=', $dateTo))
            ->pluck('amount')
            ->reduce(fn (BigDecimal $sum, $amount): BigDecimal => $sum->plus((string) $amount), BigDecimal::zero()->toScale(2));

        $entries = $paginate
            ? new LengthAwarePaginator(
                $pageRows,
                $total,
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
            )
            : $pageRows;

        return [
            'entries' => $entries,
            'openingBalance' => (string) $opening,
            'closingBalance' => (string) $closing,
        ];
    }
}
