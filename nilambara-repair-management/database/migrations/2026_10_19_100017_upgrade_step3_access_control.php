<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 64)->nullable()->unique('uq_users_username')->after('name');
            $table->boolean('is_active')->default(true)->after('password');
            $table->dateTime('last_login_at')->nullable()->after('is_active');
            $table->string('email')->nullable()->change();
        });

        $usedUsernames = [];

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user) use (&$usedUsernames): void {
            $localPart = strtolower(explode('@', (string) $user->email, 2)[0]);
            $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '-', $localPart), '.-_');
            $base = substr($base === '' ? 'user' : $base, 0, 52);
            $username = $base;
            $suffix = 1;

            while (isset($usedUsernames[$username])) {
                $username = substr($base, 0, 52).'-'.$suffix++;
            }

            $usedUsernames[$username] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('guard_name', 25)->default('web');
            $table->string('label', 100)->nullable();
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update([
                'label' => $role->name,
                'name' => $role->code,
            ]);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name'], 'uq_roles_name_guard');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('guard_name', 25)->default('web');
            $table->string('label', 150)->nullable();
        });

        DB::table('permissions')->orderBy('id')->each(function (object $permission): void {
            DB::table('permissions')->where('id', $permission->id)->update([
                'label' => $permission->name,
                'name' => $permission->code,
            ]);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name'], 'uq_permissions_name_guard');
        });

        foreach (['role_user', 'permission_user'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('model_type')->default(User::class);
            });
        }
    }

    public function down(): void
    {
        foreach (['role_user', 'permission_user'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('model_type');
            });
        }

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique('uq_permissions_name_guard');
        });

        DB::table('permissions')->orderBy('id')->each(function (object $permission): void {
            DB::table('permissions')->where('id', $permission->id)->update(['name' => $permission->label]);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn(['guard_name', 'label']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('uq_roles_name_guard');
        });

        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            DB::table('roles')->where('id', $role->id)->update(['name' => $role->label]);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['guard_name', 'label']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('uq_users_username');
            $table->dropColumn(['username', 'is_active', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
