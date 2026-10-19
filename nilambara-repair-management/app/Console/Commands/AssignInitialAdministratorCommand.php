<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignInitialAdministratorCommand extends Command
{
    protected $signature = 'app:assign-initial-admin {username : Existing enabled account username}';

    protected $description = 'Assign the Administrator role to an existing account during initial setup';

    public function handle(): int
    {
        if (User::role('administrator')->get()->contains(fn (User $user): bool => $user->hasActiveAccount())) {
            $this->error('An active administrator already exists.');

            return self::FAILURE;
        }

        $user = User::query()->where('username', strtolower($this->argument('username')))->first();

        if ($user === null || ! $user->hasActiveAccount()) {
            $this->error('The requested account does not exist or is inactive.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'administrator')->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->error('The roles and permissions have not been seeded. Run php artisan db:seed --class=RoleSeeder first.');

            return self::FAILURE;
        }

        if (! $this->confirm("Grant full administrator access to {$user->username}?", false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $role): void {
            $user->syncRoles([$role]);

            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'user.initial_administrator.assigned',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'occurred_at' => now(),
                'new_values' => ['username' => $user->username],
            ]);
        });

        $this->info("Administrator access assigned to {$user->username}.");

        return self::SUCCESS;
    }
}
