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
CREATE TABLE `customers` (
    `id` {$id},
    `customer_code` VARCHAR(40) NOT NULL,
    `customer_type` VARCHAR(64) NOT NULL,
    `customer_name` VARCHAR(255) NULL,
    `company_name` VARCHAR(255) NULL,
    `contact_person` VARCHAR(255) NULL,
    `address` TEXT NOT NULL,
    `mobile_number` VARCHAR(30) NOT NULL,
    `alternate_phone` VARCHAR(30) NULL,
    `email` VARCHAR(255) NULL,
    `opening_balance` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `special_notes` TEXT NULL,
    `registration_date` DATE NOT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_customers_68db6340` UNIQUE (`customer_code`),
    CONSTRAINT `ck_customers_1` CHECK ((`customer_type` = 'individual' AND LENGTH(TRIM(COALESCE(`customer_name`, ''))) > 0) OR (`customer_type` = 'company' AND LENGTH(TRIM(COALESCE(`company_name`, ''))) > 0)),
    CONSTRAINT `ck_customers_2` CHECK (LENGTH(TRIM(`address`)) > 0),
    CONSTRAINT `ck_customers_3` CHECK (LENGTH(TRIM(`customer_code`)) > 0),
    CONSTRAINT `ck_customers_4` CHECK (`customer_type` IN ('individual', 'company')),
    CONSTRAINT `ck_customers_5` CHECK (LENGTH(TRIM(`mobile_number`)) > 0)
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_customers_ac145587` ON `customers` (`customer_name`)');
        DB::statement('CREATE INDEX `ix_customers_a76c5cd4` ON `customers` (`company_name`)');
        DB::statement('CREATE INDEX `ix_customers_e7e1e7cc` ON `customers` (`mobile_number`)');

        DB::statement(<<<SQL
CREATE TABLE `suppliers` (
    `id` {$id},
    `supplier_code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) NULL,
    `address` TEXT NULL,
    `mobile_number` VARCHAR(30) NULL,
    `telephone_number` VARCHAR(30) NULL,
    `email` VARCHAR(255) NULL,
    `opening_balance` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `credit_terms` TEXT NULL,
    `credit_days` INTEGER UNSIGNED NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_suppliers_e1183bab` UNIQUE (`supplier_code`),
    CONSTRAINT `ck_suppliers_1` CHECK (LENGTH(TRIM(`supplier_code`)) > 0),
    CONSTRAINT `ck_suppliers_2` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `ck_suppliers_3` CHECK (`credit_days` >= 0)
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_suppliers_6ae99955` ON `suppliers` (`name`)');
        DB::statement('CREATE INDEX `ix_suppliers_e7e1e7cc` ON `suppliers` (`mobile_number`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
