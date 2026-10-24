<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InventoryBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        return $user;
    }

    private function sheet(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(array_merge([['SKU', 'Name', 'Unit Code', 'Location Code', 'Opening Quantity', 'Opening Unit Cost', 'Selling Price', 'Minimum Stock', 'Reorder Level', 'Barcode', 'Brand', 'Category']], $rows), null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'stock.xlsx', null, null, true);
    }

    private function refs(): array
    {
        $unit = UnitOfMeasure::query()->first() ?? UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Pieces', 'quantity_scale' => 0]);
        $location = StockLocation::query()->first() ?? StockLocation::create(['code' => 'MAIN', 'name' => 'Main', 'is_active' => true]);

        return [$unit->code, $location->code];
    }

    public function test_valid_file_creates_parts_with_opening_stock(): void
    {
        $admin = $this->admin();
        [$u, $l] = $this->refs();

        $this->actingAs($admin)->post(route('inventory.import.store'), ['file' => $this->sheet([
            ['IMP-1', 'Part one', $u, $l, 10, 5.5, 12, 2, 4, 'BC1', 'Acme', 'Boards'],
            ['IMP-2', 'Part two', $u, $l, null, null, 7, null, null, null, null, null],
        ])])->assertRedirect(route('inventory.index'))->assertSessionHasNoErrors();

        $this->assertSame(2, SparePart::query()->whereIn('sku', ['IMP-1', 'IMP-2'])->count());
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'opening', 'unit_cost' => '5.5000']);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('spare_part_brands', ['name' => 'Acme']);
    }

    public function test_any_bad_row_imports_nothing_and_reports_row_numbers(): void
    {
        $admin = $this->admin();
        [$u, $l] = $this->refs();
        SparePart::create(['sku' => 'DUP', 'name' => 'Existing', 'unit_id' => UnitOfMeasure::first()->id, 'default_location_id' => StockLocation::first()->id, 'selling_price' => 1, 'minimum_stock_level' => 0, 'reorder_level' => 0, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('inventory.import.store'), ['file' => $this->sheet([
            ['OK-1', 'Fine', $u, $l, 1, 1, 5, 0, 0, null, null, null],
            ['DUP', 'Existing sku', $u, $l, 1, 1, 5, 0, 0, null, null, null],
            ['BAD', 'Bad unit', 'NOPE', $l, 1, 1, 5, 0, 0, null, null, null],
        ])]);

        $response->assertRedirect();
        $errors = session('import_errors');
        $this->assertSame([3, 4], array_keys($errors));
        $this->assertDatabaseMissing('spare_parts', ['sku' => 'OK-1']);
    }

    public function test_template_download_and_permissions(): void
    {
        $admin = $this->admin();
        $this->refs();
        $this->actingAs($admin)->get(route('inventory.import'))->assertOk();
        $this->get(route('inventory.import.template'))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="stock-import-template.xlsx"');

        $tech = User::factory()->create();
                $this->actingAs($tech)->get(route('inventory.import'))->assertForbidden();
    }
}
