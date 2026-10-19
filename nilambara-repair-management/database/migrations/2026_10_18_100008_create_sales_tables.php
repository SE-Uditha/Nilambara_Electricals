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
CREATE TABLE `invoices` (
    `id` {$id},
    `invoice_number` VARCHAR(60) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `payment_terms` VARCHAR(64) NOT NULL DEFAULT 'cash',
    `due_date` DATE NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `cashier_employee_id` BIGINT UNSIGNED NOT NULL,
    `collected_by` VARCHAR(255) NULL,
    `vehicle_number` VARCHAR(40) NULL,
    `delivery_at` DATETIME NULL,
    `delivery_information` TEXT NULL,
    `special_notes` TEXT NULL,
    `document_snapshot` JSON NULL,
    `finalized_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancelled_by_user_id` BIGINT UNSIGNED NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_invoices_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_invoices_60284980` UNIQUE (`invoice_number`),
    CONSTRAINT `ck_invoices_1` CHECK (`discount_amount` <= `subtotal`),
    CONSTRAINT `ck_invoices_2` CHECK (ROUND(`subtotal` - `discount_amount`, 2) = `total`),
    CONSTRAINT `ck_invoices_3` CHECK (LENGTH(TRIM(`invoice_number`)) > 0),
    CONSTRAINT `ck_invoices_4` CHECK (`subtotal` >= 0),
    CONSTRAINT `ck_invoices_5` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_invoices_6` CHECK (`total` >= 0),
    CONSTRAINT `ck_invoices_7` CHECK (`payment_terms` IN ('cash', 'credit')),
    CONSTRAINT `ck_invoices_8` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `ck_invoices_9` CHECK (`document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)),
    CONSTRAINT `fk_invoices_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoices_161f781c` FOREIGN KEY (`cashier_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoices_cdc586e8` FOREIGN KEY (`cancelled_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_invoices_c0000d83` ON `invoices` (`invoice_date`)');
        DB::statement('CREATE INDEX `ix_invoices_feb4944b` ON `invoices` (`status`, `invoice_date`)');
        DB::statement('CREATE INDEX `ix_invoices_a7a13f4c` ON `invoices` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_invoices_782ecea6` ON `invoices` (`cashier_employee_id`)');
        DB::statement('CREATE INDEX `ix_invoices_43e48017` ON `invoices` (`cancelled_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `invoice_jobs` (
    `id` {$id},
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `job_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `job_snapshot` JSON NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_invoice_jobs_393bb4c9` UNIQUE (`invoice_id`, `repair_job_id`),
    CONSTRAINT `uq_invoice_jobs_ec756383` UNIQUE (`id`, `invoice_id`),
    CONSTRAINT `uq_invoice_jobs_d2dc6852` UNIQUE (`repair_job_id`),
    CONSTRAINT `ck_invoice_jobs_1` CHECK (`job_amount` >= 0),
    CONSTRAINT `ck_invoice_jobs_2` CHECK (`job_snapshot` IS NULL OR JSON_VALID(`job_snapshot`)),
    CONSTRAINT `fk_invoice_jobs_62d0b35a` FOREIGN KEY (`invoice_id`, `customer_id`) REFERENCES `invoices` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_jobs_05167f50` FOREIGN KEY (`repair_job_id`, `customer_id`) REFERENCES `repair_jobs` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_jobs_057bbe69` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_jobs_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_jobs_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_invoice_jobs_15d25c20` ON `invoice_jobs` (`invoice_id`)');
        DB::statement('CREATE INDEX `ix_invoice_jobs_a7a13f4c` ON `invoice_jobs` (`customer_id`)');

        DB::statement(<<<SQL
CREATE TABLE `invoice_items` (
    `id` {$id},
    `invoice_id` BIGINT UNSIGNED NOT NULL,
    `invoice_job_id` BIGINT UNSIGNED NOT NULL,
    `line_number` INTEGER UNSIGNED NOT NULL,
    `quotation_item_id` BIGINT UNSIGNED NULL,
    `item_type` VARCHAR(64) NOT NULL DEFAULT 'service',
    `spare_part_id` BIGINT UNSIGNED NULL,
    `description` TEXT NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NULL,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_invoice_items_d0e58f0e` UNIQUE (`invoice_id`, `line_number`),
    CONSTRAINT `ck_invoice_items_1` CHECK (`discount_amount` <= `quantity` * `unit_price`),
    CONSTRAINT `ck_invoice_items_2` CHECK (ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total`),
    CONSTRAINT `ck_invoice_items_3` CHECK (`line_number` >= 0),
    CONSTRAINT `ck_invoice_items_4` CHECK (`item_type` IN ('part', 'labour', 'service')),
    CONSTRAINT `ck_invoice_items_5` CHECK (`quantity` > 0),
    CONSTRAINT `ck_invoice_items_6` CHECK (`unit_price` >= 0),
    CONSTRAINT `ck_invoice_items_7` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_invoice_items_8` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_invoice_items_9` CHECK (`line_total` >= 0),
    CONSTRAINT `fk_invoice_items_cf7ebf0c` FOREIGN KEY (`invoice_job_id`, `invoice_id`) REFERENCES `invoice_jobs` (`id`, `invoice_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_items_057bbe69` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_items_5fb8988e` FOREIGN KEY (`invoice_job_id`) REFERENCES `invoice_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_items_4177160c` FOREIGN KEY (`quotation_item_id`) REFERENCES `quotation_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_invoice_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_invoice_items_15d25c20` ON `invoice_items` (`invoice_id`)');
        DB::statement('CREATE INDEX `ix_invoice_items_0d3839f6` ON `invoice_items` (`invoice_job_id`)');
        DB::statement('CREATE INDEX `ix_invoice_items_c7f10065` ON `invoice_items` (`quotation_item_id`)');
        DB::statement('CREATE INDEX `ix_invoice_items_10e3c1ce` ON `invoice_items` (`spare_part_id`)');

        DB::statement(<<<SQL
CREATE TABLE `pos_sales` (
    `id` {$id},
    `sale_number` VARCHAR(60) NOT NULL,
    `sale_at` DATETIME NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `cashier_employee_id` BIGINT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `payment_terms` VARCHAR(64) NOT NULL DEFAULT 'cash',
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `document_snapshot` JSON NULL,
    `finalized_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `notes` TEXT NULL,
    `customer_scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_pos_sales_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_pos_sales_ad3551cc` UNIQUE (`id`, `customer_scope_id`),
    CONSTRAINT `uq_pos_sales_1407226b` UNIQUE (`sale_number`),
    CONSTRAINT `ck_pos_sales_1` CHECK (`discount_amount` <= `subtotal`),
    CONSTRAINT `ck_pos_sales_2` CHECK (ROUND(`subtotal` - `discount_amount`, 2) = `total`),
    CONSTRAINT `ck_pos_sales_3` CHECK (`payment_terms` <> 'credit' OR `customer_id` IS NOT NULL),
    CONSTRAINT `ck_pos_sales_4` CHECK (`customer_scope_id` = COALESCE(`customer_id`, 0)),
    CONSTRAINT `ck_pos_sales_5` CHECK (LENGTH(TRIM(`sale_number`)) > 0),
    CONSTRAINT `ck_pos_sales_6` CHECK (`subtotal` >= 0),
    CONSTRAINT `ck_pos_sales_7` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_pos_sales_8` CHECK (`total` >= 0),
    CONSTRAINT `ck_pos_sales_9` CHECK (`payment_terms` IN ('cash', 'credit')),
    CONSTRAINT `ck_pos_sales_10` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `ck_pos_sales_11` CHECK (`document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)),
    CONSTRAINT `fk_pos_sales_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_pos_sales_161f781c` FOREIGN KEY (`cashier_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_pos_sales_a8fee2cf` ON `pos_sales` (`sale_at`)');
        DB::statement('CREATE INDEX `ix_pos_sales_a7a13f4c` ON `pos_sales` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_pos_sales_782ecea6` ON `pos_sales` (`cashier_employee_id`)');

        DB::statement(<<<SQL
CREATE TABLE `pos_sale_items` (
    `id` {$id},
    `pos_sale_id` BIGINT UNSIGNED NOT NULL,
    `line_number` INTEGER UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `description` TEXT NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NULL,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_pos_sale_items_3da73ab0` UNIQUE (`pos_sale_id`, `line_number`),
    CONSTRAINT `uq_pos_sale_items_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `ck_pos_sale_items_1` CHECK (`discount_amount` <= `quantity` * `unit_price`),
    CONSTRAINT `ck_pos_sale_items_2` CHECK (ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total`),
    CONSTRAINT `ck_pos_sale_items_3` CHECK (`line_number` >= 0),
    CONSTRAINT `ck_pos_sale_items_4` CHECK (`quantity` > 0),
    CONSTRAINT `ck_pos_sale_items_5` CHECK (`unit_price` >= 0),
    CONSTRAINT `ck_pos_sale_items_6` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_pos_sale_items_7` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_pos_sale_items_8` CHECK (`line_total` >= 0),
    CONSTRAINT `fk_pos_sale_items_3b08c716` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_pos_sale_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_pos_sale_items_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_pos_sale_items_bc1ec442` ON `pos_sale_items` (`pos_sale_id`)');
        DB::statement('CREATE INDEX `ix_pos_sale_items_10e3c1ce` ON `pos_sale_items` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_pos_sale_items_876d7bdb` ON `pos_sale_items` (`location_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoice_jobs');
        Schema::dropIfExists('invoices');
    }
};
