<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permission = DB::table('permissions')->where('name', 'jobs.delete')->value('id');
        if ($permission === null) {
            $permission = DB::table('permissions')->insertGetId([
                'name' => 'jobs.delete', 'guard_name' => 'web', 'code' => 'jobs.delete', 'label' => 'Jobs Delete',
                'module' => 'jobs', 'description' => 'Jobs Delete access.', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $role = DB::table('roles')->where('name', 'administrator')->value('id');
        if ($role !== null && ! DB::table('permission_role')->where('role_id', $role)->where('permission_id', $permission)->exists()) {
            DB::table('permission_role')->insert(['role_id' => $role, 'permission_id' => $permission, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('cache')->where('key', 'like', '%spatie.permission.cache%')->delete();
    }

    public function down(): void
    {
        // The permission is kept on rollback so existing role assignments stay valid.
    }
};
