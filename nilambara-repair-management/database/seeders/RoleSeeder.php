<?php

namespace Database\Seeders;

use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'administrator' => ['Administrator', 'System administration and business configuration.'],
            'receptionist' => ['Receptionist', 'Customer reception and repair intake.'],
            'technician' => ['Technician / Repairer', 'Inspection, repair work, and technical job updates.'],
            'cashier' => ['Cashier', 'Sales and payment collection.'],
            'storekeeper' => ['Storekeeper', 'Spare parts, purchasing, and stock records.'],
            'manager' => ['Manager', 'Business oversight and management reporting.'],
        ];

        foreach ($roles as $code => [$name, $description]) {
            Role::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $description, 'is_active' => true],
            );
        }
    }
}
