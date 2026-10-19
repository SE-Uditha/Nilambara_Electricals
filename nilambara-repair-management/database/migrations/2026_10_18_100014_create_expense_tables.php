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
CREATE TABLE `expense_categories` (
    `id` {$id},
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_expense_categories_6ae99955` UNIQUE (`name`),
    CONSTRAINT `ck_expense_categories_1` CHECK (LENGTH(TRIM(`name`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `expenses` (
    `id` {$id},
    `expense_number` VARCHAR(60) NOT NULL,
    `expense_category_id` BIGINT UNSIGNED NULL,
    `incurred_at` DATETIME NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `method` VARCHAR(64) NULL,
    `description` TEXT NOT NULL,
    `payee` VARCHAR(255) NULL,
    `external_reference` VARCHAR(100) NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_expenses_b958d1d5` UNIQUE (`expense_number`),
    CONSTRAINT `ck_expenses_1` CHECK (LENGTH(TRIM(`expense_number`)) > 0),
    CONSTRAINT `ck_expenses_2` CHECK (`amount` > 0),
    CONSTRAINT `ck_expenses_3` CHECK (`method` IN ('cash', 'bank_transfer', 'cheque')),
    CONSTRAINT `ck_expenses_4` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_expenses_65789a86` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_expenses_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_expenses_4545e26c` ON `expenses` (`incurred_at`)');
        DB::statement('CREATE INDEX `ix_expenses_478b68a9` ON `expenses` (`expense_category_id`)');
        DB::statement('CREATE INDEX `ix_expenses_06129e94` ON `expenses` (`recorded_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
