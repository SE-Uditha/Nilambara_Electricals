<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartBrand;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockImportService;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\Suppliers\Models\Supplier;
use App\Support\BusinessAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController
{
    public function index(Request $request, InventoryService $inventory)
    {
        Gate::authorize('inventory.view');
        $data = $request->validate(['q' => 'nullable|string|max:100', 'state' => 'nullable|in:low,out']);
        $query = $inventory->defaultLocationBalancesQuery()->orderBy('spare_parts.name');
        if ($data['q'] ?? null) {
            $query->where(fn ($q) => $q->where('spare_parts.name', 'like', '%'.$data['q'].'%')->orWhere('sku', 'like', '%'.$data['q'].'%')->orWhere('barcode', $data['q']));
        }
        if (($data['state'] ?? '') === 'out') {
            $query->whereRaw('COALESCE(stock_balances.available_quantity,0) <= 0');
        }
        if (($data['state'] ?? '') === 'low') {
            $query->whereRaw('COALESCE(stock_balances.available_quantity,0) <= CASE WHEN minimum_stock_level > 0 THEN minimum_stock_level ELSE reorder_level END');
        }

        return view('modules.inventory.index', ['parts' => $query->paginate(20)->withQueryString()]);
    }

    public function form(?SparePart $part = null)
    {
        Gate::authorize($part?->exists ? 'inventory.update' : 'inventory.create');

        return view('modules.inventory.form', ['part' => $part ?? new SparePart, 'units' => UnitOfMeasure::orderBy('name')->get(), 'locations' => StockLocation::where('is_active', true)->get(), 'brands' => SparePartBrand::all(), 'categories' => SparePartCategory::all(), 'suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function save(Request $request, StockWorkflowService $service, ?SparePart $part = null)
    {
        Gate::authorize($part?->exists ? 'inventory.update' : 'inventory.create');
        $data = $request->validate([
            'sku' => [$part?->exists ? 'prohibited' : 'required', 'string', 'max:80', Rule::unique('spare_parts', 'sku')],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('spare_parts', 'barcode')->ignore($part?->id)],
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:2000', 'model_compatibility' => 'nullable|string|max:2000',
            'unit_id' => 'required|integer|exists:units_of_measure,id', 'default_location_id' => 'required|integer|exists:stock_locations,id',
            'brand_id' => 'nullable|integer|exists:spare_part_brands,id', 'category_id' => 'nullable|integer|exists:spare_part_categories,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'opening_unit_cost' => [$part?->exists ? 'prohibited' : 'nullable', 'numeric', 'decimal:0,4', 'between:0,999999999999.9999'],
            'selling_price' => 'required|numeric|decimal:0,4|between:0,999999999999.9999', 'minimum_stock_level' => 'required|numeric|decimal:0,3|between:0,999999999999.999', 'reorder_level' => 'required|numeric|decimal:0,3|between:0,999999999999.999',
            'rack' => 'nullable|string|max:100', 'is_active' => 'required|boolean', 'opening_quantity' => [$part?->exists ? 'prohibited' : 'nullable', 'numeric', 'decimal:0,3', 'between:0,999999999999.999'],
        ]);
        if ($part?->exists) {
            $part = DB::transaction(function () use ($part, $data, $request) {
                $part = SparePart::whereKey($part->id)->lockForUpdate()->firstOrFail();
                // Changing unit precision after movements invalidates historical quantities.
                if ((int) $part->unit_id !== (int) $data['unit_id'] && $part->stockMovements()->exists()) {
                    throw ValidationException::withMessages(['unit_id' => 'Units cannot change after stock has been posted.']);
                }
                $old = $part->only(array_keys($data));
                $part->update($data);
                BusinessAudit::record($request->user(), 'inventory.part.updated', SparePart::class, $part->id, $old, $part->only(array_keys($data)));

                return $part;
            }, 3);
        } else {
            $part = $service->register($data, $request->user());
        }

        return redirect()->route('inventory.show', $part)->with('status', 'Part saved.');
    }

    public function show(SparePart $part)
    {
        Gate::authorize('inventory.view');

        return view('modules.inventory.show', ['part' => $part->load(['unit', 'defaultLocation']), 'movements' => $part->stockMovements()->with(['location', 'recordedByUser'])->orderByDesc('id')->paginate(25), 'locations' => StockLocation::where('is_active', true)->get()]);
    }

    public function importForm()
    {
        Gate::authorize('inventory.create');

        return view('modules.inventory.import', [
            'units' => UnitOfMeasure::orderBy('code')->get(),
            'locations' => StockLocation::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function importTemplate(StockImportService $importer)
    {
        Gate::authorize('inventory.create');

        return response($importer->template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="stock-import-template.xlsx"',
        ]);
    }

    public function import(Request $request, StockImportService $importer)
    {
        Gate::authorize('inventory.create');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        try {
            $result = $importer->import($request->file('file')->getRealPath(), $request->user());
        } catch (\PhpOffice\PhpSpreadsheet\Exception) {
            throw ValidationException::withMessages(['file' => __('inventory.import_unreadable')]);
        }
        if (isset($result['errors'])) {
            return back()->with('import_errors', $result['errors']);
        }

        return redirect()->route('inventory.index')->with('status', __('inventory.import_done', ['count' => $result['created']]));
    }

    public function reference(Request $request)
    {
        Gate::authorize('inventory.create');
        $data = $request->validate(['kind' => 'required|in:unit,location,brand,category', 'code' => 'required|string|max:30|regex:/^[A-Za-z0-9_-]+$/', 'name' => 'required|string|max:100', 'quantity_scale' => 'required|integer|between:0,3']);
        $class = ['unit' => UnitOfMeasure::class, 'location' => StockLocation::class, 'brand' => SparePartBrand::class, 'category' => SparePartCategory::class][$data['kind']];
        $column = in_array($data['kind'], ['unit', 'location'], true) ? 'code' : 'name';
        Validator::make($data, [$column => Rule::unique((new $class)->getTable(), $column)])->validate();
        $values = ['name' => $data['name']];
        if ($column === 'code') {
            $values['code'] = $data['code'];
        }
        if ($data['kind'] === 'unit') {
            $values['quantity_scale'] = $data['quantity_scale'];
        }
        $model = $class::create($values);
        BusinessAudit::record($request->user(), 'inventory.reference.created', $class, $model->id, newValues: $values);

        return back()->with('status', 'Reference saved.');
    }

    public function operation(Request $request, SparePart $part, StockWorkflowService $service, string $operation)
    {
        abort_unless(in_array($operation, ['adjust', 'count'], true), 404);
        $data = $request->validate(['location_id' => 'required|integer|exists:stock_locations,id', 'quantity' => 'required|numeric|decimal:0,3|between:-999999999999.999,999999999999.999', 'reason' => 'required|string|min:5|max:2000', 'adjustment_type' => 'required|in:correction,damaged', 'idempotency_key' => 'required|uuid']);
        $data['spare_part_id'] = $part->id;
        $service->$operation($data, $request->user());

        return back()->with('status', 'Stock operation recorded.');
    }

    public function issues(Request $request)
    {
        abort_unless($request->user()->can('inventory.issue') || $request->user()->can('jobs.repair'), 403);
        $actor = $request->user();
        $query = JobPartUsage::with(['repairJob', 'sparePart', 'location'])->orderByDesc('id');
        $jobs = RepairJob::query()->whereNull('cancelled_at');
        if (! $actor->can('inventory.issue')) {
            $query->where('requested_by_user_id', $actor->id);
        }

        return view('modules.inventory.issues', ['usages' => $query->paginate(25), 'jobs' => $jobs->orderByDesc('id')->limit(100)->get(), 'parts' => SparePart::where('is_active', true)->orderBy('name')->get(), 'locations' => StockLocation::where('is_active', true)->get()]);
    }

    public function requestPart(Request $request, StockWorkflowService $service)
    {
        $data = $request->validate(['repair_job_id' => 'required|integer|exists:repair_jobs,id', 'spare_part_id' => 'required|integer|exists:spare_parts,id', 'location_id' => 'required|integer|exists:stock_locations,id', 'quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999999.999', 'notes' => 'nullable|string|max:2000', 'idempotency_key' => 'required|uuid']);
        $service->request($data, $request->user());

        return back()->with('status', 'Part requested. Stock has not been deducted.');
    }

    public function issue(Request $request, JobPartUsage $usage, StockWorkflowService $service)
    {
        $service->issue($usage, $request->user());

        return back()->with('status', 'Part approved and issued.');
    }

    public function cancelUsage(Request $request, JobPartUsage $usage, StockWorkflowService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:2000']);
        $service->cancelUsage($usage, $data['reason'], $request->user());

        return back()->with('status', 'Request cancelled; any complete unused issue was returned to stock.');
    }
}
