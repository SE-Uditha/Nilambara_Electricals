<?php

namespace App\Modules\Reports\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class JobDataReportService
{
    public function build(array $filters): array
    {
        // Select an explicit allowlist. In particular, private_notes must never
        // enter the downloadable workbook or the scheduled cloud workbook.
        $query = DB::table('repair_jobs')
            ->join('customers', 'repair_jobs.customer_id', '=', 'customers.id')
            ->leftJoin('repair_receipts', 'repair_receipts.repair_job_id', '=', 'repair_jobs.id')
            ->leftJoin('employees as receiver', 'receiver.id', '=', 'repair_jobs.received_by_employee_id')
            ->select([
                'repair_jobs.id', 'repair_jobs.job_number', 'customers.customer_code', 'customers.customer_name',
                'customers.company_name', 'customers.customer_type',
                'repair_jobs.item_name', 'repair_jobs.brand', 'repair_jobs.model', 'repair_jobs.received_at',
                'repair_jobs.status', 'repair_jobs.reported_fault', 'repair_jobs.repair_description',
                'repair_jobs.parts_charge', 'repair_jobs.labour_charge', 'repair_jobs.total_job_amount',
                'repair_jobs.visible_damage', 'repair_jobs.special_notes as receipt_special_notes',
                'repair_jobs.completed_at', 'repair_jobs.delivered_at', 'receiver.name as received_by',
                'repair_receipts.receipt_number',
            ])
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('repair_jobs.received_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('repair_jobs.received_at', '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('repair_jobs.status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, int $id) => $q->where('repair_jobs.customer_id', $id))
            ->when($filters['employee_id'] ?? null, fn (Builder $q, int $id) => $q->where('repair_jobs.technician_id', $id))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(function (Builder $search) use ($term): void {
                $search->where('repair_jobs.job_number', 'like', '%'.$term.'%')
                    ->orWhere('customers.customer_code', 'like', '%'.$term.'%')
                    ->orWhere('customers.customer_name', 'like', '%'.$term.'%')
                    ->orWhere('customers.company_name', 'like', '%'.$term.'%')
                    ->orWhere('repair_jobs.item_name', 'like', '%'.$term.'%');
            }))
            ->orderBy('repair_jobs.received_at')->orderBy('repair_jobs.id');

        $fields = [
            'job_number' => 'job_number', 'customer_code' => 'customer_code', 'customer_name' => 'customer',
            'item_name' => 'item', 'brand' => 'brand', 'model' => 'model', 'receipt_number' => 'receipt_number',
            'received_at' => 'received', 'received_by' => 'received_by', 'status' => 'status',
            'reported_fault' => 'reported_fault', 'inspected_by' => 'inspected_by', 'inspected_at' => 'inspected_at',
            'existing_fault' => 'existing_fault', 'inspection_result' => 'inspection_result',
            'technical_information' => 'technical_information', 'repair_description' => 'repair_description',
            'repaired_by' => 'repaired_by', 'repair_completed_at' => 'repair_completed_at',
            'testing_details' => 'testing_details', 'used_spare_parts' => 'used_spare_parts',
            'selected_parts_total' => 'selected_parts_total', 'parts_charge' => 'parts_charge',
            'advance_amount' => 'advance_amount', 'labour_charge' => 'labour_charge',
            'total_job_amount' => 'repair_amount', 'visible_damage' => 'visible_damage',
            'receipt_special_notes' => 'receipt_special_notes', 'repair_special_notes' => 'repair_special_notes',
            'completed_at' => 'completed', 'delivered_at' => 'delivered_at',
        ];
        $enriched = ['inspected_by', 'inspected_at', 'existing_fault', 'inspection_result', 'technical_information',
            'repaired_by', 'repair_completed_at', 'testing_details', 'used_spare_parts', 'selected_parts_total',
            'advance_amount', 'repair_special_notes'];
        $money = ['selected_parts_total', 'parts_charge', 'advance_amount', 'labour_charge', 'total_job_amount'];
        $columns = [];
        foreach ($fields as $field => $label) {
            $columns[] = [
                'field' => $field, 'label' => __('reports.'.$label),
                'type' => in_array($field, $money, true) ? 'number' : (str_ends_with($field, '_at') ? 'date' : 'text'),
                'sortable' => ! in_array($field, $enriched, true),
            ];
        }

        return ['title' => __('reports.job-data_title'), 'columns' => $columns, 'query' => $query,
            'summary' => ['rows' => (string) (clone $query)->count()],
            'warnings' => [__('reports.job_data_help')]];
    }

    /** Batch related records so large exports do not issue queries per job. */
    public function rows(Builder $query): LazyCollection
    {
        return $query->lazy(250)->chunk(250)->flatMap(fn ($rows) => $this->enrich(collect($rows->all())));
    }

    public function enrich(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }
        $ids = $rows->pluck('id');
        $information = DB::table('job_inspections')
            ->leftJoin('employees as inspector', 'inspector.id', '=', 'job_inspections.inspected_by_employee_id')
            ->leftJoin('employees as repairer', 'repairer.id', '=', 'job_inspections.repaired_by_employee_id')
            ->whereIn('job_inspections.repair_job_id', $ids)
            ->select('job_inspections.*', 'inspector.name as inspected_by', 'repairer.name as repaired_by')
            ->orderByDesc('job_inspections.id')->get()->groupBy('repair_job_id');
        $parts = DB::table('job_part_usages')->join('spare_parts', 'spare_parts.id', '=', 'job_part_usages.spare_part_id')
            ->whereIn('job_part_usages.repair_job_id', $ids)
            ->select('job_part_usages.repair_job_id', 'job_part_usages.quantity', 'job_part_usages.unit_price', 'job_part_usages.status', 'spare_parts.name')
            ->orderBy('job_part_usages.id')->get()->groupBy('repair_job_id');
        $advances = DB::table('payment_allocations')
            ->whereIn('payment_allocations.repair_job_id', $ids)->where('target_type', 'job_advance')
            ->select('payment_allocations.repair_job_id', 'payment_allocations.amount')
            ->selectSub(DB::table('payment_allocation_releases')->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('payment_allocation_id', 'payment_allocations.id'), 'released_amount')
            ->get()->groupBy('repair_job_id');

        return $rows->map(function ($row) use ($information, $parts, $advances) {
            if ($row->customer_type === 'company') {
                $row->customer_name = $row->company_name;
            }
            $records = $information->get($row->id, collect());
            $inspection = $records->first(fn ($record) => $record->repaired_by_employee_id === null);
            $repair = $records->first(fn ($record) => $record->repaired_by_employee_id !== null);
            foreach (['inspected_by', 'inspected_at', 'existing_fault', 'inspection_result', 'technical_information'] as $field) {
                $row->{$field} = $inspection?->{$field};
            }
            foreach (['repaired_by', 'repair_completed_at', 'testing_details'] as $field) {
                $row->{$field} = $repair?->{$field};
            }
            $row->repair_special_notes = $repair?->special_notes;
            $selected = $parts->get($row->id, collect());
            $row->used_spare_parts = $selected->map(fn ($part) => $part->name.' | '.$part->quantity.' x '.($part->unit_price ?? '0').' ['.$part->status.']')->implode("\n");
            $row->selected_parts_total = (string) $selected->whereIn('status', ['draft', 'approved', 'issued'])->reduce(
                fn (BigDecimal $sum, $part) => $sum->plus(BigDecimal::of((string) $part->quantity)
                    ->multipliedBy((string) ($part->unit_price ?? '0'))->toScale(2, RoundingMode::HalfUp)), BigDecimal::zero()->toScale(2));
            $row->advance_amount = (string) $advances->get($row->id, collect())->reduce(
                fn (BigDecimal $sum, $allocation) => $sum->plus((string) $allocation->amount)->minus((string) $allocation->released_amount),
                BigDecimal::zero()->toScale(2))->toScale(2);

            return $row;
        });
    }
}
