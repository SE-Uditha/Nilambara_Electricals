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
CREATE TABLE `repair_jobs` (
    `id` {$id},
    `job_number` VARCHAR(60) NOT NULL,
    `automatic_number` VARCHAR(60) NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `brand` VARCHAR(150) NULL,
    `model` VARCHAR(150) NULL,
    `item_description` TEXT NULL,
    `reported_fault` TEXT NULL,
    `accessories_received` TEXT NULL,
    `physical_condition` TEXT NULL,
    `visible_damage` TEXT NULL,
    `job_type` VARCHAR(64) NOT NULL DEFAULT 'normal',
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `received_by_employee_id` BIGINT UNSIGNED NOT NULL,
    `technician_id` BIGINT UNSIGNED NULL,
    `repair_description` TEXT NULL,
    `labour_charge` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `labour_cost` DECIMAL(19,2) NULL,
    `parts_charge` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `parts_cost` DECIMAL(19,2) NULL,
    `total_job_amount` DECIMAL(19,2) NOT NULL DEFAULT 0,
    `completed_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `approval_status` VARCHAR(64) NOT NULL DEFAULT 'pending',
    `special_notes` TEXT NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'Received',
    `cancelled_at` DATETIME NULL,
    `cancelled_by_user_id` BIGINT UNSIGNED NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_repair_jobs_a5d450da` UNIQUE (`id`, `customer_id`),
    CONSTRAINT `uq_repair_jobs_e9c25de9` UNIQUE (`job_number`),
    CONSTRAINT `uq_repair_jobs_8bf12fb6` UNIQUE (`automatic_number`),
    CONSTRAINT `ck_repair_jobs_1` CHECK (LENGTH(TRIM(COALESCE(`brand`, ''))) > 0 OR LENGTH(TRIM(COALESCE(`model`, ''))) > 0),
    CONSTRAINT `ck_repair_jobs_2` CHECK (ROUND(`labour_charge` + `parts_charge`, 2) = `total_job_amount`),
    CONSTRAINT `ck_repair_jobs_3` CHECK (LENGTH(TRIM(`job_number`)) > 0),
    CONSTRAINT `ck_repair_jobs_4` CHECK (LENGTH(TRIM(`item_name`)) > 0),
    CONSTRAINT `ck_repair_jobs_5` CHECK (`job_type` IN ('normal', 'special', 'urgent')),
    CONSTRAINT `ck_repair_jobs_6` CHECK (`labour_charge` >= 0),
    CONSTRAINT `ck_repair_jobs_7` CHECK (`labour_cost` >= 0),
    CONSTRAINT `ck_repair_jobs_8` CHECK (`parts_charge` >= 0),
    CONSTRAINT `ck_repair_jobs_9` CHECK (`parts_cost` >= 0),
    CONSTRAINT `ck_repair_jobs_10` CHECK (`total_job_amount` >= 0),
    CONSTRAINT `ck_repair_jobs_11` CHECK (`approval_status` IN ('pending', 'approved', 'rejected')),
    CONSTRAINT `ck_repair_jobs_12` CHECK (`status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')),
    CONSTRAINT `fk_repair_jobs_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_repair_jobs_925e3749` FOREIGN KEY (`received_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_repair_jobs_5d7072fa` FOREIGN KEY (`technician_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_repair_jobs_cdc586e8` FOREIGN KEY (`cancelled_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_repair_jobs_b8aca1c7` ON `repair_jobs` (`status`, `received_at`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_67b6d081` ON `repair_jobs` (`technician_id`, `status`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_c6ae1260` ON `repair_jobs` (`item_name`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_a7a13f4c` ON `repair_jobs` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_ec48f282` ON `repair_jobs` (`received_by_employee_id`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_465640da` ON `repair_jobs` (`technician_id`)');
        DB::statement('CREATE INDEX `ix_repair_jobs_43e48017` ON `repair_jobs` (`cancelled_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `repair_receipts` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `receipt_number` VARCHAR(60) NOT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `document_snapshot` JSON NULL,
    `special_notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_repair_receipts_d2dc6852` UNIQUE (`repair_job_id`),
    CONSTRAINT `uq_repair_receipts_dc11faa3` UNIQUE (`receipt_number`),
    CONSTRAINT `ck_repair_receipts_1` CHECK (LENGTH(TRIM(`receipt_number`)) > 0),
    CONSTRAINT `ck_repair_receipts_2` CHECK (`document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)),
    CONSTRAINT `fk_repair_receipts_05167f50` FOREIGN KEY (`repair_job_id`, `customer_id`) REFERENCES `repair_jobs` (`id`, `customer_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_repair_receipts_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_repair_receipts_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_repair_receipts_a7a13f4c` ON `repair_receipts` (`customer_id`)');

        DB::statement(<<<SQL
CREATE TABLE `job_status_histories` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `previous_status` VARCHAR(64) NULL,
    `new_status` VARCHAR(64) NOT NULL,
    `changed_by_user_id` BIGINT UNSIGNED NOT NULL,
    `changed_at` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_job_status_histories_1` CHECK (`previous_status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')),
    CONSTRAINT `ck_job_status_histories_2` CHECK (`new_status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')),
    CONSTRAINT `fk_job_status_histories_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_status_histories_b7af62ac` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_job_status_histories_04832136` ON `job_status_histories` (`repair_job_id`, `changed_at`)');
        DB::statement('CREATE INDEX `ix_job_status_histories_ee56f6a4` ON `job_status_histories` (`new_status`, `changed_at`)');
        DB::statement('CREATE INDEX `ix_job_status_histories_d2dc6852` ON `job_status_histories` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_job_status_histories_69259fd8` ON `job_status_histories` (`changed_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `job_inspections` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `inspected_at` DATETIME NOT NULL,
    `inspected_by_employee_id` BIGINT UNSIGNED NOT NULL,
    `existing_fault` TEXT NULL,
    `inspection_result` TEXT NULL,
    `technical_information` TEXT NULL,
    `work_performed` TEXT NULL,
    `repaired_by_employee_id` BIGINT UNSIGNED NULL,
    `repair_completed_at` DATETIME NULL,
    `testing_details` TEXT NULL,
    `special_notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `fk_job_inspections_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_inspections_129e0005` FOREIGN KEY (`inspected_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_inspections_d8a6ca28` FOREIGN KEY (`repaired_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_job_inspections_d2dc6852` ON `job_inspections` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_job_inspections_4a26244b` ON `job_inspections` (`inspected_by_employee_id`)');
        DB::statement('CREATE INDEX `ix_job_inspections_9909258e` ON `job_inspections` (`repaired_by_employee_id`)');

        DB::statement(<<<SQL
CREATE TABLE `job_cards` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `print_template_id` BIGINT UNSIGNED NULL,
    `paper_size` VARCHAR(10) NOT NULL DEFAULT 'A6',
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_job_cards_d2dc6852` UNIQUE (`repair_job_id`),
    CONSTRAINT `ck_job_cards_1` CHECK (LENGTH(TRIM(`paper_size`)) > 0),
    CONSTRAINT `fk_job_cards_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_cards_c5fd8b20` FOREIGN KEY (`print_template_id`) REFERENCES `print_templates` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_job_cards_52a2214d` ON `job_cards` (`print_template_id`)');

        DB::statement(<<<SQL
CREATE TABLE `job_approvals` (
    `id` {$id},
    `repair_job_id` BIGINT UNSIGNED NOT NULL,
    `decision` VARCHAR(64) NOT NULL,
    `decided_at` DATETIME NOT NULL,
    `approved_by_name` VARCHAR(255) NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_job_approvals_1` CHECK (`decision` IN ('pending', 'approved', 'rejected')),
    CONSTRAINT `fk_job_approvals_60bf61a9` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_job_approvals_9a4d95ca` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_job_approvals_d2dc6852` ON `job_approvals` (`repair_job_id`)');
        DB::statement('CREATE INDEX `ix_job_approvals_06129e94` ON `job_approvals` (`recorded_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('job_approvals');
        Schema::dropIfExists('job_cards');
        Schema::dropIfExists('job_inspections');
        Schema::dropIfExists('job_status_histories');
        Schema::dropIfExists('repair_receipts');
        Schema::dropIfExists('repair_jobs');
    }
};
