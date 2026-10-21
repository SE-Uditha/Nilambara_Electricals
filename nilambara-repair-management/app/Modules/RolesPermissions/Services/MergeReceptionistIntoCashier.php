<?php

namespace App\Modules\RolesPermissions\Services;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class MergeReceptionistIntoCashier
{
    public function run(): bool
    {
        $merged = DB::transaction(function (): bool {
            $roles = Role::query()->where('guard_name', 'web')
                ->whereIn('name', ['cashier', 'receptionist'])->orderBy('id')->lockForUpdate()->get();
            $receptionist = $roles->firstWhere('name', 'receptionist');
            if ($receptionist === null) {
                return false;
            }

            $cashier = $roles->firstWhere('name', 'cashier') ?? Role::query()->create([
                'name' => 'cashier', 'code' => 'cashier', 'label' => 'Cashier',
                'guard_name' => 'web', 'is_active' => $receptionist->is_active,
                'description' => 'Customer intake, repair reception and billing.',
            ]);
            $oldPermissionIds = DB::table('permission_role')->where('role_id', $receptionist->id)
                ->pluck('permission_id')->all();
            $assignments = DB::table('role_user')->where('role_id', $receptionist->id)->lockForUpdate()->get();
            $before = [
                'retired_role' => $receptionist->only(['id', 'code', 'name', 'label', 'is_active']),
                'retired_permission_ids' => $oldPermissionIds,
                'cashier_permission_ids' => DB::table('permission_role')->where('role_id', $cashier->id)->pluck('permission_id')->all(),
                'retired_assignments' => $assignments->map(fn ($row) => (array) $row)->all(),
            ];

            foreach ($oldPermissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $cashier->id, 'permission_id' => $permissionId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($assignments as $assignment) {
                $existing = DB::table('role_user')->where('role_id', $cashier->id)->where('user_id', $assignment->user_id)->first();
                if ($existing !== null && $existing->model_type !== $assignment->model_type) {
                    throw new \RuntimeException('Conflicting account model types must be reconciled before merging roles.');
                }
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $cashier->id, 'user_id' => $assignment->user_id,
                    'model_type' => $assignment->model_type,
                    'created_at' => $assignment->created_at, 'updated_at' => now(),
                ]);
            }

            $cashier->update(['label' => 'Cashier', 'is_active' => $cashier->is_active || $receptionist->is_active]);
            DB::table('role_user')->where('role_id', $receptionist->id)->delete();
            DB::table('permission_role')->where('role_id', $receptionist->id)->delete();
            $receptionist->delete();
            AuditLog::query()->create([
                'user_id' => null, 'action' => 'roles.receptionist_merged_into_cashier',
                'entity_type' => Role::class, 'entity_id' => $cashier->id, 'occurred_at' => now(),
                'old_values' => $before,
                'new_values' => ['role' => 'cashier', 'transferred_assignments' => $assignments->count(),
                    'permission_ids' => DB::table('permission_role')->where('role_id', $cashier->id)->pluck('permission_id')->all()],
            ]);

            return true;
        });

        if ($merged) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $merged;
    }
}
