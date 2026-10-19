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
CREATE TABLE `stock_movements` (
    `id` {$id},
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `movement_type` VARCHAR(64) NOT NULL,
    `quantity_delta` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NULL,
    `occurred_at` DATETIME NOT NULL,
    `reference_number` VARCHAR(80) NOT NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL,
    `grn_item_id` BIGINT UNSIGNED NULL,
    `job_part_usage_id` BIGINT UNSIGNED NULL,
    `pos_sale_item_id` BIGINT UNSIGNED NULL,
    `return_item_id` BIGINT UNSIGNED NULL,
    `adjustment_item_id` BIGINT UNSIGNED NULL,
    `count_item_id` BIGINT UNSIGNED NULL,
    `reverses_movement_id` BIGINT UNSIGNED NULL,
    `opening_part_id` BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `movement_type` = 'opening' THEN `spare_part_id` ELSE NULL END) VIRTUAL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_movements_36e939cc` UNIQUE (`opening_part_id`, `location_id`),
    CONSTRAINT `uq_stock_movements_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `uq_stock_movements_7db4ecce` UNIQUE (`idempotency_key`),
    CONSTRAINT `uq_stock_movements_59d7f54b` UNIQUE (`grn_item_id`),
    CONSTRAINT `uq_stock_movements_53950c4b` UNIQUE (`job_part_usage_id`),
    CONSTRAINT `uq_stock_movements_eba31e8f` UNIQUE (`pos_sale_item_id`),
    CONSTRAINT `uq_stock_movements_81078615` UNIQUE (`return_item_id`),
    CONSTRAINT `uq_stock_movements_ec123f6e` UNIQUE (`adjustment_item_id`),
    CONSTRAINT `uq_stock_movements_28c2bb76` UNIQUE (`count_item_id`),
    CONSTRAINT `ck_stock_movements_1` CHECK (`quantity_delta` <> 0),
    CONSTRAINT `ck_stock_movements_2` CHECK ((`movement_type` IN ('job_issue','pos_sale','supplier_return','damaged') AND `quantity_delta` < 0) OR (`movement_type` IN ('opening','purchase_receipt','customer_return') AND `quantity_delta` > 0) OR (`movement_type` IN ('adjustment','count_correction'))),
    CONSTRAINT `ck_stock_movements_3` CHECK (`movement_type` IN ('opening', 'purchase_receipt', 'job_issue', 'pos_sale', 'customer_return', 'supplier_return', 'adjustment', 'damaged', 'count_correction')),
    CONSTRAINT `ck_stock_movements_4` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_stock_movements_5` CHECK (LENGTH(TRIM(`reference_number`)) > 0),
    CONSTRAINT `ck_stock_movements_6` CHECK (LENGTH(TRIM(`idempotency_key`)) > 0),
    CONSTRAINT `ck_stock_movements_7` CHECK ((`movement_type` = 'purchase_receipt' AND `grn_item_id` IS NOT NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'job_issue' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NOT NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'pos_sale' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NOT NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'customer_return' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NOT NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'supplier_return' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NOT NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'adjustment' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NOT NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'damaged' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NOT NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'count_correction' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NOT NULL) OR (`movement_type` = 'opening' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL)),
    CONSTRAINT `fk_stock_movements_d942adbc` FOREIGN KEY (`grn_item_id`, `spare_part_id`, `location_id`) REFERENCES `goods_received_note_items` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_037ecddd` FOREIGN KEY (`job_part_usage_id`, `spare_part_id`, `location_id`) REFERENCES `job_part_usages` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_cc6702aa` FOREIGN KEY (`pos_sale_item_id`, `spare_part_id`, `location_id`) REFERENCES `pos_sale_items` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_ad495a05` FOREIGN KEY (`return_item_id`, `spare_part_id`, `location_id`) REFERENCES `stock_return_items` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_0d8111ca` FOREIGN KEY (`adjustment_item_id`, `spare_part_id`, `location_id`) REFERENCES `stock_adjustment_items` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_f4ec6a20` FOREIGN KEY (`count_item_id`, `spare_part_id`, `location_id`) REFERENCES `stock_count_items` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_693404d5` FOREIGN KEY (`reverses_movement_id`, `spare_part_id`, `location_id`) REFERENCES `stock_movements` (`id`, `spare_part_id`, `location_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_d96c47f7` FOREIGN KEY (`grn_item_id`) REFERENCES `goods_received_note_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_d6f914d9` FOREIGN KEY (`job_part_usage_id`) REFERENCES `job_part_usages` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_c8b20681` FOREIGN KEY (`pos_sale_item_id`) REFERENCES `pos_sale_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_bbfe5ada` FOREIGN KEY (`return_item_id`) REFERENCES `stock_return_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_9ddd44ba` FOREIGN KEY (`adjustment_item_id`) REFERENCES `stock_adjustment_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_06fd9b24` FOREIGN KEY (`count_item_id`) REFERENCES `stock_count_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_stock_movements_16e4ca3a` FOREIGN KEY (`reverses_movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_stock_movements_fa634624` ON `stock_movements` (`spare_part_id`, `location_id`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_stock_movements_d084c0da` ON `stock_movements` (`movement_type`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_stock_movements_10e3c1ce` ON `stock_movements` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_stock_movements_876d7bdb` ON `stock_movements` (`location_id`)');
        DB::statement('CREATE INDEX `ix_stock_movements_06129e94` ON `stock_movements` (`recorded_by_user_id`)');
        DB::statement('CREATE INDEX `ix_stock_movements_35647365` ON `stock_movements` (`reverses_movement_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
