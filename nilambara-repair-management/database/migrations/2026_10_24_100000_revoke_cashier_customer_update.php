<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('name', 'cashier')->value('id');
        $permission = DB::table('permissions')->where('name', 'customers.update')->value('id');

        if ($role !== null && $permission !== null) {
            DB::table('permission_role')->where('role_id', $role)->where('permission_id', $permission)->delete();
        }

        DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
    }

    public function down(): void
    {
        // Direct customer editing is intentionally not restored for cashiers.
    }
};
