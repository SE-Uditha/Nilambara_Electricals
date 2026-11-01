<?php

namespace App\Modules\RepairJobs\Controllers;

use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\JobBillingService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobBillingController
{
    public function edit(RepairJob $job, Request $request, JobBillingService $billing)
    {
        $billing->assertEditable($job, $request->user());
        $charges = $job->billingCharges()->whereNull('removed_at')->orderBy('line_number')->get();
        $customTotal = $charges->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->line_total), BigDecimal::zero());

        return view('modules.repair-jobs.billing', [
            'job' => $job->load(['customer', 'jobPartUsages.sparePart']), 'charges' => $charges,
            'baseLabour' => (string) BigDecimal::of($job->labour_charge)->minus($customTotal)->toScale(2),
            'quoted' => $job->quotations()->where('preparation_status', 'issued')->exists(),
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
            'parts' => 'prohibited',
        ]);
        $billing->save($job, $data, $request->user());

        return redirect()->route('repair-jobs.billing', $job)->with('status', 'Bill saved. Review the total, then create the invoice.');
    }
}
