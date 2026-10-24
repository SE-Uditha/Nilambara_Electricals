<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartBrand;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Support\BusinessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockImportService
{
    public const MAX_ROWS = 2000;

    /** @var array<string, string> header => field */
    public const COLUMNS = [
        'SKU' => 'sku',
        'Name' => 'name',
        'Unit Code' => 'unit',
        'Location Code' => 'location',
        'Opening Quantity' => 'opening_quantity',
        'Opening Unit Cost' => 'opening_unit_cost',
        'Selling Price' => 'selling_price',
        'Minimum Stock' => 'minimum_stock_level',
        'Reorder Level' => 'reorder_level',
        'Barcode' => 'barcode',
        'Brand' => 'brand',
        'Category' => 'category',
        'Rack' => 'rack',
        'Description' => 'description',
    ];

    public function __construct(private readonly StockWorkflowService $stock) {}

    public function template(): string
    {
        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet()->setTitle('Stock');
        $ws->fromArray(array_keys(self::COLUMNS), null, 'A1');
        $unit = UnitOfMeasure::query()->orderBy('code')->first();
        $location = StockLocation::query()->where('is_active', true)->orderBy('code')->first();
        $ws->fromArray(['SP-0001', 'Sample part', $unit?->code, $location?->code, 10, 150.00, 250.00, 2, 5, '', '', '', 'A1', ''], null, 'A2');
        foreach (range('A', 'N') as $column) {
            $ws->getColumnDimension($column)->setAutoSize(true);
        }
        $ws->getStyle('A1:N1')->getFont()->setBold(true);

        $ref = $sheet->createSheet()->setTitle('Codes');
        $ref->fromArray(['Unit Code', 'Unit', null, 'Location Code', 'Location'], null, 'A1');
        $row = 2;
        foreach (UnitOfMeasure::query()->orderBy('code')->get() as $u) {
            $ref->fromArray([$u->code, $u->name], null, 'A'.$row++);
        }
        $row = 2;
        foreach (StockLocation::query()->where('is_active', true)->orderBy('code')->get() as $l) {
            $ref->fromArray([$l->code, $l->name], null, 'D'.$row++);
        }
        $sheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'stk');
        (new Xlsx($sheet))->save($path);
        $contents = file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    /**
     * Imports every row or none. Returns ['created' => int] or ['errors' => [row => [messages]]].
     *
     * @return array{created?: int, errors?: array<int, list<string>>}
     */
    public function import(string $path, User $actor): array
    {
        Gate::forUser($actor)->authorize('inventory.create');

        $rows = $this->read($path);
        if ($rows === []) {
            return ['errors' => [0 => [__('inventory.import_empty')]]];
        }
        if (count($rows) > self::MAX_ROWS) {
            return ['errors' => [0 => [__('inventory.import_too_many', ['max' => self::MAX_ROWS])]]];
        }

        $units = UnitOfMeasure::query()->get()->keyBy(fn ($u) => mb_strtolower((string) $u->code));
        $locations = StockLocation::query()->where('is_active', true)->get()->keyBy(fn ($l) => mb_strtolower((string) $l->code));
        $errors = [];
        $clean = [];
        $seenSku = [];
        $seenBarcode = [];
        foreach ($rows as $line => $row) {
            $unit = $units->get(mb_strtolower((string) $row['unit']));
            $location = $locations->get(mb_strtolower((string) $row['location']));
            $row['unit_id'] = $unit?->getKey();
            $row['default_location_id'] = $location?->getKey();
            $row['minimum_stock_level'] ??= '0';
            $row['reorder_level'] ??= '0';
            $validator = Validator::make($row, [
                'sku' => ['required', 'string', 'max:80', Rule::unique('spare_parts', 'sku')],
                'barcode' => ['nullable', 'string', 'max:100', Rule::unique('spare_parts', 'barcode')],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'unit_id' => 'required|integer',
                'default_location_id' => 'required|integer',
                'brand' => 'nullable|string|max:100',
                'category' => 'nullable|string|max:100',
                'rack' => 'nullable|string|max:100',
                'opening_quantity' => 'nullable|numeric|decimal:0,3|between:0,999999999999.999',
                'opening_unit_cost' => 'nullable|numeric|decimal:0,4|between:0,999999999999.9999',
                'selling_price' => 'required|numeric|decimal:0,4|between:0,999999999999.9999',
                'minimum_stock_level' => 'required|numeric|decimal:0,3|between:0,999999999999.999',
                'reorder_level' => 'required|numeric|decimal:0,3|between:0,999999999999.999',
            ], [
                'unit_id.required' => __('inventory.import_unknown_unit'),
                'default_location_id.required' => __('inventory.import_unknown_location'),
            ]);
            $messages = $validator->errors()->all();
            $sku = mb_strtolower((string) $row['sku']);
            if ($sku !== '' && isset($seenSku[$sku])) {
                $messages[] = __('inventory.import_duplicate_sku', ['row' => $seenSku[$sku]]);
            }
            $seenSku[$sku] ??= $line;
            if (($row['barcode'] ?? null) !== null) {
                if (isset($seenBarcode[$row['barcode']])) {
                    $messages[] = __('inventory.import_duplicate_barcode', ['row' => $seenBarcode[$row['barcode']]]);
                }
                $seenBarcode[$row['barcode']] ??= $line;
            }
            if ($messages !== []) {
                $errors[$line] = $messages;
            } else {
                $clean[$line] = $row;
            }
        }
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        DB::transaction(function () use ($clean, $actor): void {
            foreach ($clean as $row) {
                $this->stock->register([
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'unit_id' => $row['unit_id'],
                    'default_location_id' => $row['default_location_id'],
                    'brand_id' => $row['brand'] === null ? null : SparePartBrand::query()->firstOrCreate(['name' => $row['brand']])->getKey(),
                    'category_id' => $row['category'] === null ? null : SparePartCategory::query()->firstOrCreate(['name' => $row['category']])->getKey(),
                    'selling_price' => $row['selling_price'],
                    'minimum_stock_level' => $row['minimum_stock_level'],
                    'reorder_level' => $row['reorder_level'],
                    'rack' => $row['rack'],
                    'is_active' => true,
                    'opening_quantity' => $row['opening_quantity'] ?? '0',
                    'opening_unit_cost' => $row['opening_unit_cost'],
                ], $actor);
            }
            BusinessAudit::record($actor, 'inventory.bulk_imported', SparePart::class, 0, newValues: ['parts_created' => count($clean)]);
        }, 3);

        return ['created' => count($clean)];
    }

    /** @return array<int, array<string, ?string>> keyed by spreadsheet row number */
    private function read(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $table = $reader->load($path)->getSheet(0)->toArray(null, true, false, false);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($table) ?? []);
        $index = [];
        foreach (self::COLUMNS as $title => $field) {
            $position = array_search(mb_strtolower($title), $header, true);
            $index[$field] = $position === false ? null : $position;
        }
        $rows = [];
        foreach ($table as $offset => $cells) {
            $row = [];
            foreach ($index as $field => $position) {
                $value = $position === null ? null : ($cells[$position] ?? null);
                if (is_float($value) || is_int($value)) {
                    $value = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
                }
                $value = $value === null ? null : trim((string) $value);
                $row[$field] = $value === '' ? null : $value;
            }
            if (array_filter($row, fn ($v) => $v !== null) === []) {
                continue;
            }
            $rows[$offset + 2] = $row;
        }

        return $rows;
    }
}
