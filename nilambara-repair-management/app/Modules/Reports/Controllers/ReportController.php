<?php

namespace App\Modules\Reports\Controllers;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Reports\Services\ReportDataService;
use App\Modules\Reports\Services\XlsxReportExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportController
{
    private const REPORTS = [
        'repair', 'technicians', 'sales', 'collections', 'advances', 'customer-credit',
        'overdue-credit', 'supplier-outstanding', 'purchases', 'expenses', 'stock',
        'stock-movements', 'repair-parts', 'pos-parts', 'adjustments', 'purchase-history',
        'supplier-purchases', 'valuation', 'profit-estimate',
        'daily-sales', 'monthly-sales', 'yearly-sales', 'repair-income', 'invoice-sales',
        'cash-credit-sales', 'cashier-sales', 'discounts', 'refunds', 'cash-summary', 'cash-collections',
        'customer-statement', 'supplier-statement',
    ];

    public function index(Request $request): View
    {
        $actor = $request->user();

        return view('modules.reports.index', [
            'reports' => collect(self::REPORTS)->filter(fn (string $report): bool => $this->authorized($report, $actor))->values(),
        ]);
    }

    public function show(Request $request, string $report, ReportDataService $reports): View
    {
        $this->authorizeReport($report, $request->user());
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:64'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'item_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'payment_terms' => ['nullable', 'in:cash,credit'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:asc,desc'],
            'stock_state' => ['nullable', 'in:low,out'],
            'method' => ['nullable', 'in:cash,bank_transfer,cheque'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($filters['status']) && ! in_array($filters['status'], array_column(JobStatus::cases(), 'value'), true)) {
            abort(422, __('reports.invalid_status'));
        }

        $data = $reports->build($report, $filters, $request->user());
        $data = $this->sort($data, $filters);
        $rows = $data['query']->paginate(50)->withQueryString();

        return view('modules.reports.show', compact('report', 'filters', 'data', 'rows'));
    }

    public function export(Request $request, string $report, string $format, ReportDataService $reports, XlsxReportExporter $excel): Response
    {
        $this->authorizeReport($report, $request->user());
        abort_unless($request->user()->can('reports.export'), 403);
        abort_unless(in_array($format, ['xlsx', 'pdf', 'csv'], true), 404);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:64'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'item_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'payment_terms' => ['nullable', 'in:cash,credit'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:asc,desc'],
            'stock_state' => ['nullable', 'in:low,out'],
            'method' => ['nullable', 'in:cash,bank_transfer,cheque'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($filters['status']) && ! in_array($filters['status'], array_column(JobStatus::cases(), 'value'), true)) {
            abort(422, __('reports.invalid_status'));
        }

        $data = $reports->build($report, $filters, $request->user());
        $data = $this->sort($data, $filters);
        /** @var Builder $query */
        $query = $data['query'];
        $rowCount = DB::query()->fromSub((clone $query)->reorder(), 'report_rows')->count();
        if ($rowCount > ($format === 'pdf' ? 1000 : 50000)) {
            abort(422, __('reports.export_limit'));
        }
        $filename = Str::slug($report).'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            return response()->download($excel->create($query, $data['columns']), $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('modules.reports.pdf', compact('data', 'filters'))->setPaper('a4', 'landscape');
            $pdf->render();
            $pdf->getDomPDF()->getCanvas()->page_text(740, 570, '{PAGE_NUM} / {PAGE_COUNT}', null, 8);

            return $pdf->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($query, $data): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('Unable to open the report download stream.');
            }

            fputcsv($output, array_column($data['columns'], 'label'), ',', '"', '');
            foreach ($query->cursor() as $row) {
                $values = [];
                foreach ($data['columns'] as $column) {
                    $values[] = data_get($row, $column['field'], '');
                }
                fputcsv($output, array_map($this->safeCsvValue(...), $values), ',', '"', '');
            }
            fclose($output);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReport(string $report, User $actor): void
    {
        abort_unless(in_array($report, self::REPORTS, true) && $this->authorized($report, $actor), 403);
    }

    private function authorized(string $report, User $actor): bool
    {
        if (in_array($report, ['cashier-sales', 'collections', 'cash-collections', 'cash-summary'], true)
            && $actor->can('reports.sales')) {
            return true;
        }
        if (in_array($report, ['repair', 'technicians'], true)) {
            return $actor->can('reports.view') && $actor->can('jobs.view');
        }
        if (in_array($report, ['stock', 'stock-movements', 'repair-parts', 'pos-parts', 'adjustments', 'purchase-history', 'supplier-purchases', 'valuation'], true)) {
            return $actor->can('inventory.view') || $actor->can('purchases.view');
        }

        return $actor->can('reports.financial');
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    private function sort(array $data, array $filters): array
    {
        if (! empty($filters['sort'])) {
            abort_unless(in_array($filters['sort'], array_column($data['columns'], 'field'), true), 422);
            $data['query'] = DB::query()->fromSub($data['query']->reorder(), 'sorted_report')
                ->orderBy($filters['sort'], $filters['direction'] ?? 'asc');
        }

        return $data;
    }
}
