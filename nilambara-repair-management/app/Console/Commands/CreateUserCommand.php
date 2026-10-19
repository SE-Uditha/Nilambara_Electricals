<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Employees\Models\Employee;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Privately provision an employee login account';

    public function handle(): int
    {
        if (User::role('administrator')->get()->contains(fn (User $user): bool => $user->hasActiveAccount())) {
            $this->error('An active administrator already exists. Create additional accounts from the authorized user-management screen.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'administrator')->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->error('The roles and permissions have not been seeded. Run php artisan db:seed --class=RoleSeeder first.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Name')),
            'username' => Str::lower(trim((string) $this->ask('Username'))),
            'email' => Str::lower(trim((string) $this->ask('Email (optional)', ''))),
            'password' => $this->secret('Password (minimum 12 characters)', false),
            'password_confirmation' => $this->secret('Confirm password', false),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique(User::class)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->letters()->numbers(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($validator, $role): User {
            $user = User::query()->create($validator->safe()->only(['name', 'email', 'username', 'password']) + [
                'is_active' => true,
            ]);

            $employee = Employee::query()->create([
                'employee_code' => 'EMP-'.Str::upper((string) Str::ulid()),
                'name' => $user->name,
                'job_title' => 'Administrator',
                'department' => 'Management',
                'date_joined' => now()->toDateString(),
                'is_active' => true,
                'user_id' => $user->id,
            ]);

            $user->syncRoles([$role]);

            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'user.initial_administrator.created',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'occurred_at' => now(),
                'new_values' => ['username' => $user->username, 'employee_code' => $employee->employee_code],
            ]);

            return $user;
        });

        $this->info("Initial administrator account created for {$user->username}.");

        return self::SUCCESS;
    }
}
