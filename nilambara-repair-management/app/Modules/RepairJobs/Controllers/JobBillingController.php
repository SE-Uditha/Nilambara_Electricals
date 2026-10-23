<?php

namespace App\Modules\RepairJobs\Controllers;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\JobBillingService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class JobBillingController
{
    public function edit(RepairJob $job, Request $request, JobBillingService $billing)
    {
        $billing->assertEditable($job, $request->user());
        $charges = $job->billingCharges()->whereNull('removed_at')->orderBy('line_number')->get();
        $customTotal = $charges->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->line_total), BigDecimal::zero());
        $parts = $request->user()->can('inventory.view') && $request->user()->can('inventory.issue')
            ? SparePart::where('is_active', true)->whereNotNull('default_location_id')->orderBy('name')->get() : collect();
        $inventory = app(InventoryService::class);

        return view('modules.repair-jobs.billing', [
            'job' => $job->load(['customer', 'jobPartUsages.sparePart']), 'charges' => $charges,
            'baseLabour' => (string) BigDecimal::of($job->labour_charge)->minus($customTotal)->toScale(2),
            'quoted' => $job->quotations()->where('preparation_status', 'issued')->exists(),
            'parts' => $parts->map(fn ($part) => ['id' => $part->id, 'name' => $part->sku.' - '.$part->name, 'price' => $part->selling_price, 'location_id' => $part->default_location_id, 'available' => (string) $inventory->availableAtDefaultLocation($part)])->all(),
            'locations' => StockLocation::where('is_active', true)->orderBy('name')->get(),
            'submissionKey' => old('idempotency_key', (string) Str::uuid()),
        ]);
    }

    public function update(RepairJob $job, Request $request, JobBillingService $billing)
    {
        $data = $request->validate([
            'labour_charge' => 'required|numeric|decimal:0,2|between:0,999999999999.99',
            'idempotency_key' => 'required|uuid',
            'charges' => 'sometimes|array|list|max:100',
            'charges.*.description' => ['required', 'string', 'max:500', 'regex:/\S/u'],
            'charges.*.quantity' => 'required|numeric|decimal:0,3|gt:0|lte:999999999.999',
            'charges.*.unit_price' => 'required|numeric|decimal:0,4|between:0,999999999999.9999',
            'parts' => 'sometimes|array|list|max:100',
            'parts.*.spare_part_id' => 'required|integer|exists:spare_parts,id',
            'parts.*.location_id' => 'required|integer|exists:stock_locations,id',
            'parts.*.quantity' => 'required|numeric|decimal:0,3|gt:0|lte:999999999.999',
        ]);
        if ($data['parts'] ?? []) {
            Gate::authorize('inventory.issue');
        }
        $billing->save($job, $data, $request->user());

        return redirect()->route('repair-jobs.billing', $job)->with('status', ($data['parts'] ?? []) ? 'Bill saved and spare parts issued from stock. Review the total, then create the invoice.' : 'Bill saved. Review the total, then create the invoice.');
    }
}
