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
CREATE TABLE `quotations` (
    `id` {$id},
    `quotation_number` VARCHAR(60) NOT NULL,
    `revision` INTEGER UNSIGNED NOT NULL DEFAULT 1,
    `revises_quotation_id` BIGINT UNSIGNED NULL,
    `quotation_date` DATE NOT NULL,
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `proposed_repair_description` TEXT NOT NULL,
    `document_snapshot` JSON NULL,
    `labour_charge` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `subtotal` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `valid_until` DATE NULL,
    `terms_and_conditions` TEXT NULL,
    `approval_status` VARCHAR(64) NOT NULL DEFAULT 'pending',
    `status` VARCHAR(64) NOT NULL DEFAULT 'draft',
    `created_by_user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_quotations_4bfa8930` UNIQUE (`id`, `repair_job_id`, `customer_id`),
    CONSTRAINT `uq_quotations_73fda6a0` UNIQUE (`quotation_number`),
    CONSTRAINT `uq_quotations_b0fb8224` UNIQUE (`revises_quotation_id`),
    CONSTRAINT `ck_quotations_1` CHECK (`revision` > 0),
    CONSTRAINT `ck_quotations_2` CHECK (`discount_amount` <= `subtotal`),
    CONSTRAINT `ck_quotations_3` CHECK (ROUND(`subtotal` - `discount_amount`, 2) = `total`),
    CONSTRAINT `ck_quotations_4` CHECK (`valid_until` IS NULL OR `valid_until` >= `quotation_date`),
    CONSTRAINT `ck_quotations_5` CHECK (LENGTH(TRIM(`quotation_number`)) > 0),
    CONSTRAINT `ck_quotations_6` CHECK (`revision` >= 0),
    CONSTRAINT `ck_quotations_7` CHECK (`document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)),
    CONSTRAINT `ck_quotations_8` CHECK (`labour_charge` >= 0),
    CONSTRAINT `ck_quotations_9` CHECK (`subtotal` >= 0),
    CONSTRAINT `ck_quotations_10` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_quotations_11` CHECK (`total` >= 0),
    CONSTRAINT `ck_quotations_12` CHECK (`approval_status` IN ('pending', 'approved', 'rejected')),
    CONSTRAINT `ck_quotations_13` CHECK (`status` IN ('draft', 'finalized', 'cancelled')),
    CONSTRAINT `fk_quotations_05167f50` FOREIGN KEY (`repair_job_id`, `customer_id`) REFERENCES `repair_jobs` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotations_69de8f01` FOREIGN KEY (`revises_quotation_id`, `repair_job_id`, `customer_id`) REFERENCES `quotations` (`id`, `repair_job_id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotations_6699dfc4` FOREIGN KEY (`revises_quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotations_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotations_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotations_3fd75b0f` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_quotations_0bd5d101` ON `quotations` (`quotation_date`)');
        DB::statement('CREATE INDEX `ix_quotations_d2dc6852` ON `quotations` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_quotations_a7a13f4c` ON `quotations` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_quotations_ae7720e8` ON `quotations` (`created_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `quotation_items` (
    `id` {$id},
    `quotation_id` BIGINT UNSIGNED NOT NULL,
    `line_number` INTEGER UNSIGNED NOT NULL,
    `item_type` VARCHAR(64) NOT NULL DEFAULT 'part',
    `spare_part_id` BIGINT UNSIGNED NULL,
    `description` TEXT NOT NULL,
    `quantity` DECIMAL(18,3) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(19,4) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `line_total` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_quotation_items_9d5c5c2c` UNIQUE (`quotation_id`, `line_number`),
    CONSTRAINT `ck_quotation_items_1` CHECK (`line_number` > 0),
    CONSTRAINT `ck_quotation_items_2` CHECK (`discount_amount` <= `quantity` * `unit_price`),
    CONSTRAINT `ck_quotation_items_3` CHECK (ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total`),
    CONSTRAINT `ck_quotation_items_4` CHECK (`line_number` >= 0),
    CONSTRAINT `ck_quotation_items_5` CHECK (`item_type` IN ('part', 'labour', 'service')),
    CONSTRAINT `ck_quotation_items_6` CHECK (`quantity` > 0),
    CONSTRAINT `ck_quotation_items_7` CHECK (`unit_price` >= 0),
    CONSTRAINT `ck_quotation_items_8` CHECK (`discount_amount` >= 0),
    CONSTRAINT `ck_quotation_items_9` CHECK (`line_total` >= 0),
    CONSTRAINT `fk_quotation_items_ae50c22d` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotation_items_4e44a58a` FOREIGN KEY (`spare_part_id`) REFERENCES `spare_parts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_quotation_items_596c572d` ON `quotation_items` (`quotation_id`)');
        DB::statement('CREATE INDEX `ix_quotation_items_10e3c1ce` ON `quotation_items` (`spare_part_id`)');

        DB::statement(<<<SQL
CREATE TABLE `quotation_approvals` (
    `id` {$id},
    `quotation_id` BIGINT UNSIGNED NOT NULL,
    `decision` VARCHAR(64) NOT NULL,
    `decided_at` DATETIME NOT NULL,
    `customer_contact_name` VARCHAR(255) NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_quotation_approvals_1` CHECK (`decision` IN ('pending', 'approved', 'rejected')),
    CONSTRAINT `fk_quotation_approvals_ae50c22d` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_quotation_approvals_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_quotation_approvals_596c572d` ON `quotation_approvals` (`quotation_id`)');
        DB::statement('CREATE INDEX `ix_quotation_approvals_06129e94` ON `quotation_approvals` (`recorded_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_approvals');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
