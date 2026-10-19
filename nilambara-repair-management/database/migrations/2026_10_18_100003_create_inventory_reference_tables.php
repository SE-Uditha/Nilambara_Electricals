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
CREATE TABLE `spare_part_categories` (
    `id` {$id},
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_spare_part_categories_6ae99955` UNIQUE (`name`),
    CONSTRAINT `ck_spare_part_categories_1` CHECK (LENGTH(TRIM(`name`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `spare_part_brands` (
    `id` {$id},
    `name` VARCHAR(150) NOT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_spare_part_brands_6ae99955` UNIQUE (`name`),
    CONSTRAINT `ck_spare_part_brands_1` CHECK (LENGTH(TRIM(`name`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `units_of_measure` (
    `id` {$id},
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `quantity_scale` INTEGER UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_units_of_measure_e6fb0621` UNIQUE (`code`),
    CONSTRAINT `ck_units_of_measure_1` CHECK (`quantity_scale` BETWEEN 0 AND 3),
    CONSTRAINT `ck_units_of_measure_2` CHECK (LENGTH(TRIM(`code`)) > 0),
    CONSTRAINT `ck_units_of_measure_3` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `ck_units_of_measure_4` CHECK (`quantity_scale` >= 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `stock_locations` (
    `id` {$id},
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_stock_locations_e6fb0621` UNIQUE (`code`),
    CONSTRAINT `ck_stock_locations_1` CHECK (LENGTH(TRIM(`code`)) > 0),
    CONSTRAINT `ck_stock_locations_2` CHECK (LENGTH(TRIM(`name`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `spare_parts` (
    `id` {$id},
    `sku` VARCHAR(80) NOT NULL,
    `barcode` VARCHAR(100) NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `brand_id` BIGINT UNSIGNED NULL,
    `category_id` BIGINT UNSIGNED NULL,
    `model_compatibility` TEXT NULL,
    `unit_id` BIGINT UNSIGNED NOT NULL,
    `selling_price` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `minimum_stock_level` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `reorder_level` DECIMAL(18,3) NOT NULL DEFAULT 0,
    `supplier_id` BIGINT UNSIGNED NULL,
    `default_location_id` BIGINT UNSIGNED NULL,
    `rack` VARCHAR(100) NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_spare_parts_cf559ae3` UNIQUE (`sku`),
    CONSTRAINT `uq_spare_parts_006b53f0` UNIQUE (`barcode`),
    CONSTRAINT `ck_spare_parts_1` CHECK (LENGTH(TRIM(`sku`)) > 0),
    CONSTRAINT `ck_spare_parts_2` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `ck_spare_parts_3` CHECK (`selling_price` >= 0),
    CONSTRAINT `ck_spare_parts_4` CHECK (`minimum_stock_level` >= 0),
    CONSTRAINT `ck_spare_parts_5` CHECK (`reorder_level` >= 0),
    CONSTRAINT `fk_spare_parts_e3b91f6f` FOREIGN KEY (`brand_id`) REFERENCES `spare_part_brands` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_spare_parts_9c0aa519` FOREIGN KEY (`category_id`) REFERENCES `spare_part_categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_spare_parts_4463c0db` FOREIGN KEY (`unit_id`) REFERENCES `units_of_measure` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_spare_parts_b55dd59c` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_spare_parts_03881a5f` FOREIGN KEY (`default_location_id`) REFERENCES `stock_locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_spare_parts_6ae99955` ON `spare_parts` (`name`)');
        DB::statement('CREATE INDEX `ix_spare_parts_a5d20765` ON `spare_parts` (`brand_id`)');
        DB::statement('CREATE INDEX `ix_spare_parts_9c4e4a89` ON `spare_parts` (`category_id`)');
        DB::statement('CREATE INDEX `ix_spare_parts_4252c4be` ON `spare_parts` (`unit_id`)');
        DB::statement('CREATE INDEX `ix_spare_parts_2bc0d2ca` ON `spare_parts` (`supplier_id`)');
        DB::statement('CREATE INDEX `ix_spare_parts_4ea168a9` ON `spare_parts` (`default_location_id`)');

        DB::statement(<<<SQL
CREATE TABLE `document_sequences` (
    `id` {$id},
    `scope` VARCHAR(80) NOT NULL,
    `prefix` VARCHAR(20) NOT NULL,
    `padding` INTEGER UNSIGNED NOT NULL DEFAULT 5,
    `next_value` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `description` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_document_sequences_aacd3103` UNIQUE (`scope`),
    CONSTRAINT `ck_document_sequences_1` CHECK (`next_value` > 0),
    CONSTRAINT `ck_document_sequences_2` CHECK (`padding` BETWEEN 1 AND 18),
    CONSTRAINT `ck_document_sequences_3` CHECK (LENGTH(TRIM(`scope`)) > 0),
    CONSTRAINT `ck_document_sequences_4` CHECK (LENGTH(TRIM(`prefix`)) > 0),
    CONSTRAINT `ck_document_sequences_5` CHECK (`padding` >= 0)
){$suffix}
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('spare_part_brands');
        Schema::dropIfExists('spare_part_categories');
    }
};
