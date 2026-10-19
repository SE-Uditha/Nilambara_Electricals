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
CREATE TABLE `job_part_usages` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NULL,
    `unit_price` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `requested_by_user_id` BIGINT UNSIGNED NOT NULL,
    `approved_at` DATETIME NULL,
    `approved_by_user_id` BIGINT UNSIGNED NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_job_part_usages_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `uq_job_part_usages_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `ck_job_part_usages_1` CHECK (`quantity` > 0),
    CONSTRAINT `ck_job_part_usages_2` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_job_part_usages_3` CHECK (`unit_price` >= 0),
    CONSTRAINT `ck_job_part_usages_4` CHECK (`status` IN ('draft', 'approved', 'issued', 'cancelled')),
    CONSTRAINT `ck_job_part_usages_5` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `fk_job_part_usages_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_part_usages_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_part_usages_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_part_usages_ae99d6ba` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_part_usages_62831278` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_job_part_usages_d2dc6852` ON `job_part_usages` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_job_part_usages_10e3c1ce` ON `job_part_usages` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_job_part_usages_876d7bdb` ON `job_part_usages` (`location_id`)');
        DB::statement('CREATE INDEX `ix_job_part_usages_1d9e5646` ON `job_part_usages` (`requested_by_user_id`)');
        DB::statement('CREATE INDEX `ix_job_part_usages_46df84f0` ON `job_part_usages` (`approved_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_adjustments` (
    `id` {$id},
    `adjustment_number` VARCHAR(60) NOT NULL,
    `adjusted_at` DATETIME NOT NULL,
    `adjustment_type` VARCHAR(64) NOT NULL DEFAULT 'correction',
    `reason` TEXT NOT NULL,
    `created_by_user_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `approved_at` DATETIME NULL,
    `approved_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_adjustments_4fe26025` UNIQUE (`adjustment_number`),
    CONSTRAINT `ck_stock_adjustments_1` CHECK (LENGTH(TRIM(`adjustment_number`)) > 0),
    CONSTRAINT `ck_stock_adjustments_2` CHECK (`adjustment_type` IN ('correction', 'damaged')),
    CONSTRAINT `ck_stock_adjustments_3` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_stock_adjustments_3fd75b0f` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_adjustments_62831278` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_adjustments_ae7720e8` ON `stock_adjustments` (`created_by_user_id`)');
        DB::statement('CREATE INDEX `ix_stock_adjustments_46df84f0` ON `stock_adjustments` (`approved_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_adjustment_items` (
    `id` {$id},
    `stock_adjustment_id` BIGINT UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `quantity_delta` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_adjustment_items_1e5daea3` UNIQUE (`stock_adjustment_id`, `spare_part_id`, `location_id`),
    CONSTRAINT `uq_stock_adjustment_items_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `ck_stock_adjustment_items_1` CHECK (`quantity_delta` <> 0),
    CONSTRAINT `fk_stock_adjustment_items_2f581b3b` FOREIGN KEY (`stock_adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_adjustment_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_adjustment_items_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_adjustment_items_a140820a` ON `stock_adjustment_items` (`stock_adjustment_id`)');
        DB::statement('CREATE INDEX `ix_stock_adjustment_items_10e3c1ce` ON `stock_adjustment_items` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_stock_adjustment_items_876d7bdb` ON `stock_adjustment_items` (`location_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_counts` (
    `id` {$id},
    `count_number` VARCHAR(60) NOT NULL,
    `counted_at` DATETIME NOT NULL,
    `counted_by_user_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_counts_568cf27e` UNIQUE (`count_number`),
    CONSTRAINT `ck_stock_counts_1` CHECK (LENGTH(TRIM(`count_number`)) > 0),
    CONSTRAINT `ck_stock_counts_2` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_stock_counts_3d834744` FOREIGN KEY (`counted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_counts_c0622bff` ON `stock_counts` (`counted_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_count_items` (
    `id` {$id},
    `stock_count_id` BIGINT UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `expected_quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `counted_quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_count_items_ee7e6c5a` UNIQUE (`stock_count_id`, `spare_part_id`, `location_id`),
    CONSTRAINT `uq_stock_count_items_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `ck_stock_count_items_1` CHECK (`expected_quantity` >= 0),
    CONSTRAINT `ck_stock_count_items_2` CHECK (`counted_quantity` >= 0),
    CONSTRAINT `fk_stock_count_items_1f54342a` FOREIGN KEY (`stock_count_id`) REFERENCES `stock_counts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_count_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_count_items_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_count_items_942c8300` ON `stock_count_items` (`stock_count_id`)');
        DB::statement('CREATE INDEX `ix_stock_count_items_10e3c1ce` ON `stock_count_items` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_stock_count_items_876d7bdb` ON `stock_count_items` (`location_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_returns` (
    `id` {$id},
    `return_number` VARCHAR(60) NOT NULL,
    `return_type` VARCHAR(64) NOT NULL,
    `repair_job_id` BIGINT UNSIGNED NULL,
    `pos_sale_id` BIGINT UNSIGNED NULL,
    `purchase_id` BIGINT UNSIGNED NULL,
    `returned_at` DATETIME NOT NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `reason` TEXT NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_returns_855a7dba` UNIQUE (`return_number`),
    CONSTRAINT `ck_stock_returns_1` CHECK ((`return_type` = 'repair_return' AND `repair_job_id` IS NOT NULL AND `pos_sale_id` IS NULL AND `purchase_id` IS NULL) OR (`return_type` = 'pos_return' AND `pos_sale_id` IS NOT NULL AND `repair_job_id` IS NULL AND `purchase_id` IS NULL) OR (`return_type` = 'supplier_return' AND `purchase_id` IS NOT NULL AND `repair_job_id` IS NULL AND `pos_sale_id` IS NULL)),
    CONSTRAINT `ck_stock_returns_2` CHECK (LENGTH(TRIM(`return_number`)) > 0),
    CONSTRAINT `ck_stock_returns_3` CHECK (`return_type` IN ('repair_return', 'pos_return', 'supplier_return')),
    CONSTRAINT `ck_stock_returns_4` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_stock_returns_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_returns_3b08c716` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_returns_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_returns_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_returns_d2dc6852` ON `stock_returns` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_stock_returns_bc1ec442` ON `stock_returns` (`pos_sale_id`)');
        DB::statement('CREATE INDEX `ix_stock_returns_86cc2ebe` ON `stock_returns` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_stock_returns_06129e94` ON `stock_returns` (`recorded_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `stock_return_items` (
    `id` {$id},
    `stock_return_id` BIGINT UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NULL,
    `condition` VARCHAR(64) NOT NULL DEFAULT 'resalable',
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_return_items_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `ck_stock_return_items_1` CHECK (`quantity` > 0),
    CONSTRAINT `ck_stock_return_items_2` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_stock_return_items_3` CHECK (`condition` IN ('resalable', 'damaged')),
    CONSTRAINT `fk_stock_return_items_c51b15bc` FOREIGN KEY (`stock_return_id`) REFERENCES `stock_returns` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_return_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_return_items_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_return_items_7032963c` ON `stock_return_items` (`stock_return_id`)');
        DB::statement('CREATE INDEX `ix_stock_return_items_10e3c1ce` ON `stock_return_items` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_stock_return_items_876d7bdb` ON `stock_return_items` (`location_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_return_items');
        Schema::dropIfExists('stock_returns');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('job_part_usages');
    }
};
