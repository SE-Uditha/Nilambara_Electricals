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
CREATE TABLE `payments` (
    `id` {$id},
    `payment_number` VARCHAR(60) NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `payment_type` VARCHAR(64) NOT NULL DEFAULT 'invoice',
    `method` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `paid_at` DATETIME NOT NULL,
    `received_by_employee_id` BIGINT UNSIGNED NOT NULL,
    `bank_reference` VARCHAR(100) NULL,
    `cheque_number` VARCHAR(100) NULL,
    `cheque_date` DATE NULL,
    `bank_name` VARCHAR(150) NULL,
    `notes` TEXT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `customer_scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_payments_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_payments_ad3551cc` UNIQUE (`id`, `customer_scope_id`),
    CONSTRAINT `uq_payments_39743c6d` UNIQUE (`payment_number`),
    CONSTRAINT `uq_payments_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_payments_1` CHECK (`payment_type` = 'pos' OR `customer_id` IS NOT NULL),
    CONSTRAINT `ck_payments_2` CHECK (`customer_scope_id` = COALESCE(`customer_id`, 0)),
    CONSTRAINT `ck_payments_3` CHECK (LENGTH(TRIM(`payment_number`)) > 0),
    CONSTRAINT `ck_payments_4` CHECK (`payment_type` IN ('advance', 'invoice', 'credit_collection', 'pos')),
    CONSTRAINT `ck_payments_5` CHECK (`method` IN ('cash', 'bank_transfer', 'cheque')),
    CONSTRAINT `ck_payments_6` CHECK (`amount` > 0),
    CONSTRAINT `ck_payments_7` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_payments_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payments_925e3749` FOREIGN KEY (`received_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_payments_7c3ebe1b` ON `payments` (`paid_at`)');
        DB::statement('CREATE INDEX `ix_payments_6b26a8f9` ON `payments` (`method`, `paid_at`)');
        DB::statement('CREATE INDEX `ix_payments_a7a13f4c` ON `payments` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_payments_ec48f282` ON `payments` (`received_by_employee_id`)');

        DB::statement(<<<SQL
CREATE TABLE `payment_allocations` (
    `id` {$id},
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `target_type` VARCHAR(64) NOT NULL,
    `repair_job_id` BIGINT UNSIGNED NULL,
    `invoice_id` BIGINT UNSIGNED NULL,
    `pos_sale_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `allocated_at` DATETIME NOT NULL,
    `allocated_by_user_id` BIGINT UNSIGNED NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `customer_scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_payment_allocations_ae6c2db1` UNIQUE (`id`, `payment_id`),
    CONSTRAINT `uq_payment_allocations_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_payment_allocations_1` CHECK ((`target_type` = 'job_advance' AND `repair_job_id` IS NOT NULL AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `customer_id` IS NOT NULL) OR (`target_type` = 'invoice' AND `invoice_id` IS NOT NULL AND `repair_job_id` IS NULL AND `pos_sale_id` IS NULL AND `customer_id` IS NOT NULL) OR (`target_type` = 'pos_sale' AND `pos_sale_id` IS NOT NULL AND `repair_job_id` IS NULL AND `invoice_id` IS NULL) OR (`target_type` = 'opening_balance' AND `customer_id` IS NOT NULL AND `repair_job_id` IS NULL AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL)),
    CONSTRAINT `ck_payment_allocations_2` CHECK (`customer_scope_id` = COALESCE(`customer_id`, 0)),
    CONSTRAINT `ck_payment_allocations_3` CHECK (`target_type` IN ('job_advance', 'invoice', 'pos_sale', 'opening_balance')),
    CONSTRAINT `ck_payment_allocations_4` CHECK (`amount` > 0),
    CONSTRAINT `ck_payment_allocations_5` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_payment_allocations_5bcc665a` FOREIGN KEY (`payment_id`, `customer_id`) REFERENCES `payments` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_05167f50` FOREIGN KEY (`repair_job_id`, `customer_id`) REFERENCES `repair_jobs` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_62d0b35a` FOREIGN KEY (`invoice_id`, `customer_id`) REFERENCES `invoices` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_4355babb` FOREIGN KEY (`pos_sale_id`, `customer_id`) REFERENCES `pos_sales` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_5f5d56da` FOREIGN KEY (`payment_id`, `customer_scope_id`) REFERENCES `payments` (`id`, `customer_scope_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_e4f5269a` FOREIGN KEY (`pos_sale_id`, `customer_scope_id`) REFERENCES `pos_sales` (`id`, `customer_scope_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_178f8cd3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_057bbe69` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_3b08c716` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocations_b38fee2d` FOREIGN KEY (`allocated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_payment_allocations_fcaec7df` ON `payment_allocations` (`payment_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocations_a7a13f4c` ON `payment_allocations` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocations_d2dc6852` ON `payment_allocations` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocations_15d25c20` ON `payment_allocations` (`invoice_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocations_bc1ec442` ON `payment_allocations` (`pos_sale_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocations_997c1d72` ON `payment_allocations` (`allocated_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `payment_reversals` (
    `id` {$id},
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `reversal_type` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `method` VARCHAR(64) NULL,
    `reversed_at` DATETIME NOT NULL,
    `reversed_by_user_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `external_reference` VARCHAR(100) NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `customer_scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_payment_reversals_ae6c2db1` UNIQUE (`id`, `payment_id`),
    CONSTRAINT `uq_payment_reversals_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_payment_reversals_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_payment_reversals_1` CHECK (`customer_scope_id` = COALESCE(`customer_id`, 0)),
    CONSTRAINT `ck_payment_reversals_2` CHECK (`reversal_type` IN ('refund', 'reversal')),
    CONSTRAINT `ck_payment_reversals_3` CHECK (`amount` > 0),
    CONSTRAINT `ck_payment_reversals_4` CHECK (`method` IN ('cash', 'bank_transfer', 'cheque')),
    CONSTRAINT `ck_payment_reversals_5` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_payment_reversals_5f5d56da` FOREIGN KEY (`payment_id`, `customer_scope_id`) REFERENCES `payments` (`id`, `customer_scope_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_reversals_178f8cd3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_reversals_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_reversals_2913e132` FOREIGN KEY (`reversed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_payment_reversals_fcaec7df` ON `payment_reversals` (`payment_id`)');
        DB::statement('CREATE INDEX `ix_payment_reversals_a7a13f4c` ON `payment_reversals` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_payment_reversals_07fdd642` ON `payment_reversals` (`reversed_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `payment_allocation_releases` (
    `id` {$id},
    `payment_allocation_id` BIGINT UNSIGNED NOT NULL,
    `payment_id` BIGINT UNSIGNED NOT NULL,
    `payment_reversal_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `reason_type` VARCHAR(64) NOT NULL,
    `released_at` DATETIME NOT NULL,
    `released_by_user_id` BIGINT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_payment_allocation_releases_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_payment_allocation_releases_1` CHECK (`amount` > 0),
    CONSTRAINT `ck_payment_allocation_releases_2` CHECK (`reason_type` IN ('reapplication', 'refund', 'reversal')),
    CONSTRAINT `ck_payment_allocation_releases_3` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_payment_allocation_releases_cb606553` FOREIGN KEY (`payment_allocation_id`, `payment_id`) REFERENCES `payment_allocations` (`id`, `payment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocation_releases_b1bc50fb` FOREIGN KEY (`payment_reversal_id`, `payment_id`) REFERENCES `payment_reversals` (`id`, `payment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocation_releases_2d295180` FOREIGN KEY (`payment_allocation_id`) REFERENCES `payment_allocations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocation_releases_178f8cd3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocation_releases_7565880b` FOREIGN KEY (`payment_reversal_id`) REFERENCES `payment_reversals` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_payment_allocation_releases_31567845` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_payment_allocation_releases_c33ff68d` ON `payment_allocation_releases` (`payment_allocation_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocation_releases_fcaec7df` ON `payment_allocation_releases` (`payment_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocation_releases_5b312e0c` ON `payment_allocation_releases` (`payment_reversal_id`)');
        DB::statement('CREATE INDEX `ix_payment_allocation_releases_e78980db` ON `payment_allocation_releases` (`released_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocation_releases');
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
