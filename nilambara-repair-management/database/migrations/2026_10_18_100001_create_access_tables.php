<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Native DDL preserves CHECK and composite FK semantics across both supported test drivers.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        DB::statement(<<<SQL
CREATE TABLE `roles` (
    `id` {$id},
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_roles_e6fb0621` UNIQUE (`code`),
    CONSTRAINT `ck_roles_1` CHECK (LENGTH(TRIM(`code`)) > 0),
    CONSTRAINT `ck_roles_2` CHECK (LENGTH(TRIM(`name`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `permissions` (
    `id` {$id},
    `code` VARCHAR(120) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `module` VARCHAR(64) NOT NULL,
    `description` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_permissions_e6fb0621` UNIQUE (`code`),
    CONSTRAINT `ck_permissions_1` CHECK (LENGTH(TRIM(`code`)) > 0),
    CONSTRAINT `ck_permissions_2` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `ck_permissions_3` CHECK (LENGTH(TRIM(`module`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `role_user` (
    `id` {$id},
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_role_user_721adde8` UNIQUE (`user_id`, `role_id`),
    CONSTRAINT `fk_role_user_6ec16f9c` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_role_user_037f480d` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_role_user_cace4a15` ON `role_user` (`user_id`)');
        DB::statement('CREATE INDEX `ix_role_user_b36bcfe0` ON `role_user` (`role_id`)');

        DB::statement(<<<SQL
CREATE TABLE `permission_role` (
    `id` {$id},
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_permission_role_1fd58356` UNIQUE (`role_id`, `permission_id`),
    CONSTRAINT `fk_permission_role_037f480d` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_permission_role_e0f5ced1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_permission_role_b36bcfe0` ON `permission_role` (`role_id`)');
        DB::statement('CREATE INDEX `ix_permission_role_3b8b97af` ON `permission_role` (`permission_id`)');

        DB::statement(<<<SQL
CREATE TABLE `permission_user` (
    `id` {$id},
    `user_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `effect` VARCHAR(64) NOT NULL DEFAULT 'allow',
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_permission_user_6d378d26` UNIQUE (`user_id`, `permission_id`),
    CONSTRAINT `ck_permission_user_1` CHECK (`effect` IN ('allow', 'deny')),
    CONSTRAINT `fk_permission_user_6ec16f9c` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_permission_user_e0f5ced1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_permission_user_cace4a15` ON `permission_user` (`user_id`)');
        DB::statement('CREATE INDEX `ix_permission_user_3b8b97af` ON `permission_user` (`permission_id`)');

        DB::statement(<<<SQL
CREATE TABLE `employees` (
    `id` {$id},
    `employee_code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `address` TEXT NULL,
    `mobile_number` VARCHAR(30) NULL,
    `job_title` VARCHAR(100) NULL,
    `department` VARCHAR(100) NULL,
    `date_joined` DATE NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `user_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_employees_348a4a98` UNIQUE (`employee_code`),
    CONSTRAINT `uq_employees_cace4a15` UNIQUE (`user_id`),
    CONSTRAINT `ck_employees_1` CHECK (LENGTH(TRIM(`employee_code`)) > 0),
    CONSTRAINT `ck_employees_2` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `fk_employees_6ec16f9c` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
