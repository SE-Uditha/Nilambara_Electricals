<?php

namespace App\Modules\RolesPermissions\Services;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class MergeOperationsIntoCashier
{
    public function run(): bool
    {
        return DB::transaction(function (): bool {
            $manager = $this->mergeRole('manager');
            $storekeeper = $this->mergeRole('storekeeper');

            return $manager || $storekeeper;
        });
    }

    private function mergeRole(string $name): bool
    {
        $merged = DB::transaction(function () use ($name): bool {
            $roles = Role::query()->where('guard_name', 'web')
                ->whereIn('name', ['cashier', $name])->orderBy('id')->lockForUpdate()->get();
            $retired = $roles->firstWhere('name', $name);
            if ($retired === null) {
                return false;
            }

            $cashier = $roles->firstWhere('name', 'cashier') ?? Role::query()->create([
                'name' => 'cashier', 'code' => 'cashier', 'label' => 'Cashier',
                'guard_name' => 'web', 'is_active' => $retired->is_active,
                'description' => 'Customer intake, billing, management, purchasing and inventory.',
            ]);
            $oldPermissionIds = DB::table('permission_role')->where('role_id', $retired->id)
                ->pluck('permission_id')->all();
            $assignments = DB::table('role_user')->where('role_id', $retired->id)->lockForUpdate()->get();
            $before = [
                'retired_role' => $retired->only(['id', 'code', 'name', 'label', 'is_active']),
                'retired_permission_ids' => $oldPermissionIds,
                'cashier_permission_ids' => DB::table('permission_role')->where('role_id', $cashier->id)->pluck('permission_id')->all(),
                'existing_cashier_user_ids' => DB::table('role_user')->where('role_id', $cashier->id)->pluck('user_id')->all(),
                'accounts' => DB::table('users')->whereIn('id', $assignments->pluck('user_id'))->get(['id', 'username', 'is_active'])->map(fn ($row) => (array) $row)->all(),
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

            // Retain accounts and employee references; disable obsolete operational logins.
            // Protected Administrator/Technician and existing Cashier accounts stay active.
            foreach ($assignments as $assignment) {
                $hasRetainedRole = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->where('user_id', $assignment->user_id)
                    ->whereIn('roles.name', ['administrator', 'technician'])->exists();
                $wasCashier = in_array($assignment->user_id, $before['existing_cashier_user_ids']);
                if (! $hasRetainedRole && ! $wasCashier) {
                    DB::table('users')->where('id', $assignment->user_id)->update(['is_active' => false, 'updated_at' => now()]);
                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->where('user_id', $assignment->user_id)->delete();
                    }
                }
            }

            $cashier->update(['label' => 'Cashier']);
            DB::table('role_user')->where('role_id', $retired->id)->delete();
            DB::table('permission_role')->where('role_id', $retired->id)->delete();
            $retired->delete();
            AuditLog::query()->create([
                'user_id' => null, 'action' => 'roles.'.$name.'_merged_into_cashier',
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
