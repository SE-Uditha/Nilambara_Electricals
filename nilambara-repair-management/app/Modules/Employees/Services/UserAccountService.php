<?php

namespace App\Modules\Employees\Services;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UserAccountService
{
    public function assignRole(User $target, Role $role, User $actor): void
    {
        $this->preventSelfPrivilegeChange($target, $actor);
        $this->ensureAdministratorRemains($target, ! $target->hasRole('administrator') || $role->name === 'administrator', $target->is_active);

        DB::transaction(function () use ($target, $role, $actor): void {
            $oldRoles = $target->roles->pluck('name')->all();
            $target->syncRoles([$role]);
            $this->audit($actor, 'user.role.changed', $target, [
                'before' => $oldRoles,
                'after' => [$role->name],
            ]);
        });
    }

    public function setEnabled(User $target, bool $enabled, User $actor): void
    {
        if ($target->is($actor) && ! $enabled) {
            throw ValidationException::withMessages(['is_active' => 'You cannot disable your own account.']);
        }

        if (! $enabled) {
            $this->ensureAdministratorRemains($target, false, false);
        }

        DB::transaction(function () use ($target, $enabled, $actor): void {
            $target->forceFill(['is_active' => $enabled])->save();

            if (! $enabled) {
                DB::table('sessions')->where('user_id', $target->getKey())->delete();
            }

            $this->audit($actor, $enabled ? 'user.account.enabled' : 'user.account.disabled', $target, [
                'is_active' => $enabled,
            ]);
        });
    }

    public function resetPassword(User $target, string $password, User $actor): void
    {
        DB::transaction(function () use ($target, $password, $actor): void {
            $target->forceFill(['password' => Hash::make($password)])->save();
            DB::table('sessions')->where('user_id', $target->getKey())->delete();

            $this->audit($actor, 'user.password.reset', $target, ['sessions_invalidated' => true]);
        });
    }

    /**
     * @param  array<int, array{permission_id: int, effect: string}>  $overrides
     */
    public function syncPermissionOverrides(User $target, array $overrides, User $actor): void
    {
        $this->preventSelfPrivilegeChange($target, $actor);

        if ($target->hasRole('administrator')) {
            throw ValidationException::withMessages([
                'permissions' => 'Administrator accounts always inherit all available permissions.',
            ]);
        }

        DB::transaction(function () use ($target, $overrides, $actor): void {
            DB::table('permission_user')
                ->where('user_id', $target->getKey())
                ->where('model_type', $target->getMorphClass())
                ->delete();

            foreach ($overrides as $override) {
                DB::table('permission_user')->updateOrInsert(
                    [
                        'user_id' => $target->getKey(),
                        'model_type' => $target->getMorphClass(),
                        'permission_id' => $override['permission_id'],
                    ],
                    [
                        'effect' => $override['effect'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            $target->unsetRelation('permissions');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->audit($actor, 'user.permissions.changed', $target, [
                'overrides' => count($overrides),
            ]);
        });
    }

    private function preventSelfPrivilegeChange(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot change your own roles or permission overrides.']);
        }
    }

    private function ensureAdministratorRemains(User $target, bool $willRemainAdministrator, bool $willRemainActive): void
    {
        if (! $target->hasRole('administrator') || ($willRemainAdministrator && $willRemainActive)) {
            return;
        }

        $activeAdministrators = User::role('administrator')->whereKeyNot($target->getKey())->get()
            ->filter(fn (User $user): bool => $user->hasActiveAccount())
            ->count();

        if ($activeAdministrators === 0) {
            throw ValidationException::withMessages([
                'role_id' => 'The last active administrator cannot be disabled or have the administrator role removed.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(User $actor, string $action, User $target, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => User::class,
            'entity_id' => $target->getKey(),
            'occurred_at' => now(),
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
        ]);
    }
}
