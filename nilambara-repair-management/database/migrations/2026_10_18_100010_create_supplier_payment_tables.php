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
CREATE TABLE `supplier_payments` (
    `id` {$id},
    `payment_number` VARCHAR(60) NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `method` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `paid_at` DATETIME NOT NULL,
    `paid_by_user_id` BIGINT UNSIGNED NOT NULL,
    `bank_reference` VARCHAR(100) NULL,
    `cheque_number` VARCHAR(100) NULL,
    `cheque_date` DATE NULL,
    `notes` TEXT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_supplier_payments_4e0db050` UNIQUE (`id`, `supplier_id`),
    CONSTRAINT `uq_supplier_payments_39743c6d` UNIQUE (`payment_number`),
    CONSTRAINT `uq_supplier_payments_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_supplier_payments_1` CHECK (LENGTH(TRIM(`payment_number`)) > 0),
    CONSTRAINT `ck_supplier_payments_2` CHECK (`method` IN ('cash', 'bank_transfer', 'cheque')),
    CONSTRAINT `ck_supplier_payments_3` CHECK (`amount` > 0),
    CONSTRAINT `ck_supplier_payments_4` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_supplier_payments_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payments_45728c77` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_supplier_payments_7c3ebe1b` ON `supplier_payments` (`paid_at`)');
        DB::statement('CREATE INDEX `ix_supplier_payments_2bc0d2ca` ON `supplier_payments` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payments_38e2e5db` ON `supplier_payments` (`paid_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `supplier_payment_allocations` (
    `id` {$id},
    `supplier_payment_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `purchase_id` BIGINT UNSIGNED NULL,
    `target_type` VARCHAR(64) NOT NULL DEFAULT 'purchase',
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `allocated_at` DATETIME NOT NULL,
    `allocated_by_user_id` BIGINT UNSIGNED NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_supplier_payment_allocations_3f75acb8` UNIQUE (`id`, `supplier_payment_id`),
    CONSTRAINT `uq_supplier_payment_allocations_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_supplier_payment_allocations_1` CHECK ((`target_type` = 'purchase' AND `purchase_id` IS NOT NULL) OR (`target_type` = 'opening_balance' AND `purchase_id` IS NULL)),
    CONSTRAINT `ck_supplier_payment_allocations_2` CHECK (`target_type` IN ('purchase', 'opening_balance')),
    CONSTRAINT `ck_supplier_payment_allocations_3` CHECK (`amount` > 0),
    CONSTRAINT `ck_supplier_payment_allocations_4` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_supplier_payment_allocations_e24bcc51` FOREIGN KEY (`supplier_payment_id`, `supplier_id`) REFERENCES `supplier_payments` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_allocations_3e1bac6a` FOREIGN KEY (`purchase_id`, `supplier_id`) REFERENCES `purchases` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_allocations_9585dc83` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_allocations_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_allocations_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_allocations_b38fee2d` FOREIGN KEY (`allocated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_supplier_payment_allocations_69c70222` ON `supplier_payment_allocations` (`supplier_payment_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payment_allocations_2bc0d2ca` ON `supplier_payment_allocations` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payment_allocations_86cc2ebe` ON `supplier_payment_allocations` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payment_allocations_997c1d72` ON `supplier_payment_allocations` (`allocated_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `supplier_payment_reversals` (
    `id` {$id},
    `supplier_payment_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `reversal_type` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `reversed_at` DATETIME NOT NULL,
    `reversed_by_user_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_supplier_payment_reversals_3f75acb8` UNIQUE (`id`, `supplier_payment_id`),
    CONSTRAINT `uq_supplier_payment_reversals_4e0db050` UNIQUE (`id`, `supplier_id`),
    CONSTRAINT `uq_supplier_payment_reversals_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_supplier_payment_reversals_1` CHECK (`reversal_type` IN ('refund', 'reversal')),
    CONSTRAINT `ck_supplier_payment_reversals_2` CHECK (`amount` > 0),
    CONSTRAINT `ck_supplier_payment_reversals_3` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_supplier_payment_reversals_e24bcc51` FOREIGN KEY (`supplier_payment_id`, `supplier_id`) REFERENCES `supplier_payments` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_reversals_9585dc83` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_reversals_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_payment_reversals_2913e132` FOREIGN KEY (`reversed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_supplier_payment_reversals_69c70222` ON `supplier_payment_reversals` (`supplier_payment_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payment_reversals_2bc0d2ca` ON `supplier_payment_reversals` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_supplier_payment_reversals_07fdd642` ON `supplier_payment_reversals` (`reversed_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `supplier_allocation_releases` (
    `id` {$id},
    `supplier_payment_allocation_id` BIGINT UNSIGNED NOT NULL,
    `supplier_payment_id` BIGINT UNSIGNED NOT NULL,
    `supplier_payment_reversal_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `released_at` DATETIME NOT NULL,
    `released_by_user_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_supplier_allocation_releases_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_supplier_allocation_releases_1` CHECK (`amount` > 0),
    CONSTRAINT `ck_supplier_allocation_releases_2` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_supplier_allocation_releases_fd2690eb` FOREIGN KEY (`supplier_payment_allocation_id`, `supplier_payment_id`) REFERENCES `supplier_payment_allocations` (`id`, `supplier_payment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_allocation_releases_2e689253` FOREIGN KEY (`supplier_payment_reversal_id`, `supplier_payment_id`) REFERENCES `supplier_payment_reversals` (`id`, `supplier_payment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_allocation_releases_49f6ec38` FOREIGN KEY (`supplier_payment_allocation_id`) REFERENCES `supplier_payment_allocations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_allocation_releases_9585dc83` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_allocation_releases_995ae35c` FOREIGN KEY (`supplier_payment_reversal_id`) REFERENCES `supplier_payment_reversals` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_allocation_releases_31567845` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_supplier_allocation_releases_ba299898` ON `supplier_allocation_releases` (`supplier_payment_allocation_id`)');
        DB::statement('CREATE INDEX `ix_supplier_allocation_releases_69c70222` ON `supplier_allocation_releases` (`supplier_payment_id`)');
        DB::statement('CREATE INDEX `ix_supplier_allocation_releases_3ec7caab` ON `supplier_allocation_releases` (`supplier_payment_reversal_id`)');
        DB::statement('CREATE INDEX `ix_supplier_allocation_releases_e78980db` ON `supplier_allocation_releases` (`released_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_allocation_releases');
        Schema::dropIfExists('supplier_payment_reversals');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
    }
};
