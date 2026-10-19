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
CREATE TABLE `customer_account_entries` (
    `id` {$id},
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `entry_type` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `occurred_at` DATETIME NOT NULL,
    `reference_number` VARCHAR(80) NOT NULL,
    `description` TEXT NOT NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `invoice_id` BIGINT UNSIGNED NULL,
    `pos_sale_id` BIGINT UNSIGNED NULL,
    `payment_id` BIGINT UNSIGNED NULL,
    `payment_reversal_id` BIGINT UNSIGNED NULL,
    `reverses_entry_id` BIGINT UNSIGNED NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `opening_owner_id` BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `entry_type` = 'opening' THEN `customer_id` ELSE NULL END) VIRTUAL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_customer_account_entries_1de3c56e` UNIQUE (`opening_owner_id`),
    CONSTRAINT `uq_customer_account_entries_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_customer_account_entries_15d25c20` UNIQUE (`invoice_id`),
    CONSTRAINT `uq_customer_account_entries_bc1ec442` UNIQUE (`pos_sale_id`),
    CONSTRAINT `uq_customer_account_entries_fcaec7df` UNIQUE (`payment_id`),
    CONSTRAINT `uq_customer_account_entries_5b312e0c` UNIQUE (`payment_reversal_id`),
    CONSTRAINT `uq_customer_account_entries_fc740855` UNIQUE (`reverses_entry_id`),
    CONSTRAINT `uq_customer_account_entries_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_customer_account_entries_1` CHECK (`amount` <> 0),
    CONSTRAINT `ck_customer_account_entries_2` CHECK ((`entry_type` IN ('invoice', 'pos_sale', 'refund') AND `amount` > 0) OR (`entry_type` = 'payment' AND `amount` < 0) OR (`entry_type` IN ('opening', 'adjustment', 'reversal'))),
    CONSTRAINT `ck_customer_account_entries_3` CHECK (`entry_type` IN ('opening', 'invoice', 'pos_sale', 'payment', 'refund', 'reversal', 'adjustment')),
    CONSTRAINT `ck_customer_account_entries_4` CHECK (LENGTH(TRIM(`reference_number`)) > 0),
    CONSTRAINT `ck_customer_account_entries_5` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `ck_customer_account_entries_6` CHECK ((`entry_type` = 'invoice' AND `invoice_id` IS NOT NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'pos_sale' AND `invoice_id` IS NULL AND `pos_sale_id` IS NOT NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'payment' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NOT NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'refund' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NOT NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'reversal' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NOT NULL) OR (`entry_type` IN ('opening','adjustment') AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL)),
    CONSTRAINT `fk_customer_account_entries_5585485a` FOREIGN KEY (`payment_reversal_id`, `customer_id`) REFERENCES `payment_reversals` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_62d0b35a` FOREIGN KEY (`invoice_id`, `customer_id`) REFERENCES `invoices` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_4355babb` FOREIGN KEY (`pos_sale_id`, `customer_id`) REFERENCES `pos_sales` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_5bcc665a` FOREIGN KEY (`payment_id`, `customer_id`) REFERENCES `payments` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_afce3f4c` FOREIGN KEY (`reverses_entry_id`, `customer_id`) REFERENCES `customer_account_entries` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_057bbe69` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_3b08c716` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_178f8cd3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_7565880b` FOREIGN KEY (`payment_reversal_id`) REFERENCES `payment_reversals` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_customer_account_entries_07ad7fc5` FOREIGN KEY (`reverses_entry_id`) REFERENCES `customer_account_entries` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_customer_account_entries_d947b0a0` ON `customer_account_entries` (`customer_id`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_customer_account_entries_a7a13f4c` ON `customer_account_entries` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_customer_account_entries_06129e94` ON `customer_account_entries` (`recorded_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `supplier_account_entries` (
    `id` {$id},
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `entry_type` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `occurred_at` DATETIME NOT NULL,
    `reference_number` VARCHAR(80) NOT NULL,
    `description` TEXT NOT NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `purchase_id` BIGINT UNSIGNED NULL,
    `supplier_payment_id` BIGINT UNSIGNED NULL,
    `supplier_payment_reversal_id` BIGINT UNSIGNED NULL,
    `reverses_entry_id` BIGINT UNSIGNED NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `opening_owner_id` BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `entry_type` = 'opening' THEN `supplier_id` ELSE NULL END) VIRTUAL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_supplier_account_entries_1de3c56e` UNIQUE (`opening_owner_id`),
    CONSTRAINT `uq_supplier_account_entries_4e0db050` UNIQUE (`id`, `supplier_id`),
    CONSTRAINT `uq_supplier_account_entries_86cc2ebe` UNIQUE (`purchase_id`),
    CONSTRAINT `uq_supplier_account_entries_69c70222` UNIQUE (`supplier_payment_id`),
    CONSTRAINT `uq_supplier_account_entries_3ec7caab` UNIQUE (`supplier_payment_reversal_id`),
    CONSTRAINT `uq_supplier_account_entries_fc740855` UNIQUE (`reverses_entry_id`),
    CONSTRAINT `uq_supplier_account_entries_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_supplier_account_entries_1` CHECK (`amount` <> 0),
    CONSTRAINT `ck_supplier_account_entries_2` CHECK ((`entry_type` IN ('purchase', 'refund') AND `amount` > 0) OR (`entry_type` = 'payment' AND `amount` < 0) OR (`entry_type` IN ('opening', 'adjustment', 'reversal'))),
    CONSTRAINT `ck_supplier_account_entries_3` CHECK (`entry_type` IN ('opening', 'purchase', 'payment', 'refund', 'reversal', 'adjustment')),
    CONSTRAINT `ck_supplier_account_entries_4` CHECK (LENGTH(TRIM(`reference_number`)) > 0),
    CONSTRAINT `ck_supplier_account_entries_5` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `ck_supplier_account_entries_6` CHECK ((`entry_type` = 'purchase' AND `purchase_id` IS NOT NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'payment' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NOT NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'refund' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NOT NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'reversal' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NOT NULL) OR (`entry_type` IN ('opening','adjustment') AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL)),
    CONSTRAINT `fk_supplier_account_entries_10afd45a` FOREIGN KEY (`supplier_payment_reversal_id`, `supplier_id`) REFERENCES `supplier_payment_reversals` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_3e1bac6a` FOREIGN KEY (`purchase_id`, `supplier_id`) REFERENCES `purchases` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_e24bcc51` FOREIGN KEY (`supplier_payment_id`, `supplier_id`) REFERENCES `supplier_payments` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_80141e8c` FOREIGN KEY (`reverses_entry_id`, `supplier_id`) REFERENCES `supplier_account_entries` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_9585dc83` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_995ae35c` FOREIGN KEY (`supplier_payment_reversal_id`) REFERENCES `supplier_payment_reversals` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_supplier_account_entries_6da695da` FOREIGN KEY (`reverses_entry_id`) REFERENCES `supplier_account_entries` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_supplier_account_entries_a3fe3b0b` ON `supplier_account_entries` (`supplier_id`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_supplier_account_entries_2bc0d2ca` ON `supplier_account_entries` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_supplier_account_entries_06129e94` ON `supplier_account_entries` (`recorded_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_account_entries');
        Schema::dropIfExists('customer_account_entries');
    }
};
