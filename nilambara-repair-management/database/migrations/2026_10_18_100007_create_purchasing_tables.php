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
CREATE TABLE `purchases` (
    `id` {$id},
    `purchase_number` VARCHAR(60) NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `supplier_invoice_number` VARCHAR(100) NULL,
    `purchase_date` DATE NOT NULL,
    `subtotal` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `other_costs` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `payment_terms` VARCHAR(64) NOT NULL DEFAULT 'cash',
    `due_date` DATE NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `created_by_user_id` BIGINT UNSIGNED NOT NULL,
    `finalized_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_purchases_95419c78` UNIQUE (`supplier_id`, `supplier_invoice_number`),
    CONSTRAINT `uq_purchases_4e0db050` UNIQUE (`id`, `supplier_id`),
    CONSTRAINT `uq_purchases_6c59959c` UNIQUE (`purchase_number`),
    CONSTRAINT `ck_purchases_1` CHECK (`discount_amount` <= `subtotal`),
    CONSTRAINT `ck_purchases_2` CHECK (ROUND(`subtotal` - `discount_amount` + `other_costs`, 2) = `total`),
    CONSTRAINT `ck_purchases_3` CHECK (LENGTH(TRIM(`purchase_number`)) > 0),
    CONSTRAINT `ck_purchases_4` CHECK (`subtotal` >= 0),
    CONSTRAINT `ck_purchases_5` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_purchases_6` CHECK (`other_costs` >= 0),
    CONSTRAINT `ck_purchases_7` CHECK (`total` >= 0),
    CONSTRAINT `ck_purchases_8` CHECK (`payment_terms` IN ('cash', 'credit')),
    CONSTRAINT `ck_purchases_9` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_purchases_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_purchases_3fd75b0f` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_purchases_d9987142` ON `purchases` (`purchase_date`)');
        DB::statement('CREATE INDEX `ix_purchases_2bc0d2ca` ON `purchases` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_purchases_ae7720e8` ON `purchases` (`created_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `purchase_items` (
    `id` {$id},
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `line_number` INTEGER UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `description` TEXT NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_purchase_items_ed918c72` UNIQUE (`purchase_id`, `line_number`),
    CONSTRAINT `uq_purchase_items_951d6c0e` UNIQUE (`id`, `purchase_id`),
    CONSTRAINT `uq_purchase_items_26e56352` UNIQUE (`id`, `spare_part_id`),
    CONSTRAINT `ck_purchase_items_1` CHECK (`line_number` > 0),
    CONSTRAINT `ck_purchase_items_2` CHECK (`discount_amount` <= `quantity` * `unit_cost`),
    CONSTRAINT `ck_purchase_items_3` CHECK (ROUND(`quantity` * `unit_cost` - `discount_amount`, 2) = `line_total`),
    CONSTRAINT `ck_purchase_items_4` CHECK (`line_number` >= 0),
    CONSTRAINT `ck_purchase_items_5` CHECK (`quantity` > 0),
    CONSTRAINT `ck_purchase_items_6` CHECK (`unit_cost` >= 0),
    CONSTRAINT `ck_purchase_items_7` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_purchase_items_8` CHECK (`line_total` >= 0),
    CONSTRAINT `fk_purchase_items_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_purchase_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_purchase_items_86cc2ebe` ON `purchase_items` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_purchase_items_10e3c1ce` ON `purchase_items` (`spare_part_id`)');

        DB::statement(<<<SQL
CREATE TABLE `goods_received_notes` (
    `id` {$id},
    `grn_number` VARCHAR(60) NOT NULL,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `received_at` DATETIME NOT NULL,
    `received_by_employee_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `finalized_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_goods_received_notes_951d6c0e` UNIQUE (`id`, `purchase_id`),
    CONSTRAINT `uq_goods_received_notes_da00b996` UNIQUE (`grn_number`),
    CONSTRAINT `ck_goods_received_notes_1` CHECK (LENGTH(TRIM(`grn_number`)) > 0),
    CONSTRAINT `ck_goods_received_notes_2` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_goods_received_notes_3e1bac6a` FOREIGN KEY (`purchase_id`, `supplier_id`) REFERENCES `purchases` (`id`, `supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_notes_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_notes_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_notes_925e3749` FOREIGN KEY (`received_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_goods_received_notes_86cc2ebe` ON `goods_received_notes` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_notes_2bc0d2ca` ON `goods_received_notes` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_notes_ec48f282` ON `goods_received_notes` (`received_by_employee_id`)');

        DB::statement(<<<SQL
CREATE TABLE `goods_received_note_items` (
    `id` {$id},
    `goods_received_note_id` BIGINT UNSIGNED NOT NULL,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `purchase_item_id` BIGINT UNSIGNED NOT NULL,
    `spare_part_id` BIGINT UNSIGNED NOT NULL,
    `location_id` BIGINT UNSIGNED NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_goods_received_note_items_156a1229` UNIQUE (`goods_received_note_id`, `purchase_item_id`, `location_id`),
    CONSTRAINT `uq_goods_received_note_items_d9f67aa6` UNIQUE (`id`, `spare_part_id`, `location_id`),
    CONSTRAINT `ck_goods_received_note_items_1` CHECK (`quantity` > 0),
    CONSTRAINT `ck_goods_received_note_items_2` CHECK (`unit_cost` >= 0),
    CONSTRAINT `fk_goods_received_note_items_f23d6f0e` FOREIGN KEY (`goods_received_note_id`, `purchase_id`) REFERENCES `goods_received_notes` (`id`, `purchase_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_d8bea874` FOREIGN KEY (`purchase_item_id`, `purchase_id`) REFERENCES `purchase_items` (`id`, `purchase_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_25d8e711` FOREIGN KEY (`purchase_item_id`, `spare_part_id`) REFERENCES `purchase_items` (`id`, `spare_part_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_c7673552` FOREIGN KEY (`goods_received_note_id`) REFERENCES `goods_received_notes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_06b0c46c` FOREIGN KEY (`purchase_item_id`) REFERENCES `purchase_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_goods_received_note_items_7173818d` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_goods_received_note_items_fc342424` ON `goods_received_note_items` (`goods_received_note_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_note_items_86cc2ebe` ON `goods_received_note_items` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_note_items_e7e6cd38` ON `goods_received_note_items` (`purchase_item_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_note_items_10e3c1ce` ON `goods_received_note_items` (`spare_part_id`)');
        DB::statement('CREATE INDEX `ix_goods_received_note_items_876d7bdb` ON `goods_received_note_items` (`location_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
