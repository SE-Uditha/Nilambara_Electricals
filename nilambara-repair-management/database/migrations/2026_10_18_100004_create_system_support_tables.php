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
CREATE TABLE `print_templates` (
    `id` {$id},
    `code` VARCHAR(80) NOT NULL,
    `version` INTEGER UNSIGNED NOT NULL DEFAULT 1,
    `document_type` VARCHAR(50) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `business_name` VARCHAR(255) NULL,
    `business_address` TEXT NULL,
    `telephone_number` VARCHAR(40) NULL,
    `logo_path` VARCHAR(255) NULL,
    `document_title` VARCHAR(255) NULL,
    `paper_size` VARCHAR(64) NOT NULL DEFAULT 'A5',
    `orientation` VARCHAR(64) NOT NULL DEFAULT 'portrait',
    `thermal_width_mm` DECIMAL(19,2) NULL,
    `font_size_pt` DECIMAL(19,2) NOT NULL DEFAULT 10,
    `margin_top_mm` DECIMAL(19,2) NOT NULL DEFAULT 5,
    `margin_right_mm` DECIMAL(19,2) NOT NULL DEFAULT 5,
    `margin_bottom_mm` DECIMAL(19,2) NOT NULL DEFAULT 5,
    `margin_left_mm` DECIMAL(19,2) NOT NULL DEFAULT 5,
    `table_widths` JSON NULL,
    `signature_areas` JSON NULL,
    `footer` TEXT NULL,
    `duplicate_copy_label` VARCHAR(100) NOT NULL DEFAULT 'Duplicate Copy',
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_print_templates_1f1f8ab9` UNIQUE (`code`, `version`),
    CONSTRAINT `ck_print_templates_1` CHECK (`version` > 0),
    CONSTRAINT `ck_print_templates_2` CHECK (LENGTH(TRIM(`code`)) > 0),
    CONSTRAINT `ck_print_templates_3` CHECK (`version` >= 0),
    CONSTRAINT `ck_print_templates_4` CHECK (LENGTH(TRIM(`document_type`)) > 0),
    CONSTRAINT `ck_print_templates_5` CHECK (LENGTH(TRIM(`name`)) > 0),
    CONSTRAINT `ck_print_templates_6` CHECK (`paper_size` IN ('A4', 'A5', 'A6', 'thermal')),
    CONSTRAINT `ck_print_templates_7` CHECK (`orientation` IN ('portrait', 'landscape')),
    CONSTRAINT `ck_print_templates_8` CHECK (`thermal_width_mm` >= 0),
    CONSTRAINT `ck_print_templates_9` CHECK (`font_size_pt` >= 0),
    CONSTRAINT `ck_print_templates_10` CHECK (`margin_top_mm` >= 0),
    CONSTRAINT `ck_print_templates_11` CHECK (`margin_right_mm` >= 0),
    CONSTRAINT `ck_print_templates_12` CHECK (`margin_bottom_mm` >= 0),
    CONSTRAINT `ck_print_templates_13` CHECK (`margin_left_mm` >= 0),
    CONSTRAINT `ck_print_templates_14` CHECK (`table_widths` IS NULL OR JSON_VALID(`table_widths`)),
    CONSTRAINT `ck_print_templates_15` CHECK (`signature_areas` IS NULL OR JSON_VALID(`signature_areas`)),
    CONSTRAINT `ck_print_templates_16` CHECK (LENGTH(TRIM(`duplicate_copy_label`)) > 0)
){$suffix}
SQL);

        DB::statement(<<<SQL
CREATE TABLE `system_settings` (
    `id` {$id},
    `group` VARCHAR(60) NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    `value` JSON NOT NULL,
    `value_type` VARCHAR(30) NOT NULL DEFAULT 'string',
    `description` TEXT NULL,
    `updated_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `uq_system_settings_3b49c75a` UNIQUE (`group`, `key`),
    CONSTRAINT `ck_system_settings_1` CHECK (LENGTH(TRIM(`group`)) > 0),
    CONSTRAINT `ck_system_settings_2` CHECK (LENGTH(TRIM(`key`)) > 0),
    CONSTRAINT `ck_system_settings_3` CHECK (`value` IS NULL OR JSON_VALID(`value`)),
    CONSTRAINT `ck_system_settings_4` CHECK (LENGTH(TRIM(`value_type`)) > 0),
    CONSTRAINT `fk_system_settings_388a3aee` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_system_settings_7a022eb2` ON `system_settings` (`updated_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `backup_histories` (
    `id` {$id},
    `backup_at` DATETIME NOT NULL,
    `file_reference` VARCHAR(255) NOT NULL,
    `backup_type` VARCHAR(64) NOT NULL DEFAULT 'manual',
    `status` VARCHAR(64) NOT NULL DEFAULT 'pending',
    `storage_location` VARCHAR(100) NOT NULL,
    `checksum_sha256` VARCHAR(64) NULL,
    `verified_at` DATETIME NULL,
    `error_details` TEXT NULL,
    `initiated_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_backup_histories_1` CHECK (LENGTH(TRIM(`file_reference`)) > 0),
    CONSTRAINT `ck_backup_histories_2` CHECK (`backup_type` IN ('manual', 'scheduled')),
    CONSTRAINT `ck_backup_histories_3` CHECK (`status` IN ('pending', 'running', 'completed', 'failed')),
    CONSTRAINT `ck_backup_histories_4` CHECK (LENGTH(TRIM(`storage_location`)) > 0),
    CONSTRAINT `fk_backup_histories_44b73ded` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_backup_histories_5af47fbb` ON `backup_histories` (`backup_at`)');
        DB::statement('CREATE INDEX `ix_backup_histories_48a3661d` ON `backup_histories` (`status`)');
        DB::statement('CREATE INDEX `ix_backup_histories_5fb88229` ON `backup_histories` (`initiated_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `backup_restores` (
    `id` {$id},
    `backup_history_id` BIGINT UNSIGNED NOT NULL,
    `initiated_by_user_id` BIGINT UNSIGNED NOT NULL,
    `confirmed_at` DATETIME NOT NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `status` VARCHAR(64) NOT NULL DEFAULT 'pending',
    `error_details` TEXT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_backup_restores_1` CHECK (`status` IN ('pending', 'verified', 'running', 'completed', 'failed')),
    CONSTRAINT `fk_backup_restores_fe28e790` FOREIGN KEY (`backup_history_id`) REFERENCES `backup_histories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_backup_restores_44b73ded` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_backup_restores_b9e34ae4` ON `backup_restores` (`backup_history_id`)');
        DB::statement('CREATE INDEX `ix_backup_restores_5fb88229` ON `backup_restores` (`initiated_by_user_id`)');

        DB::statement(<<<SQL
CREATE TABLE `audit_logs` (
    `id` {$id},
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(150) NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `occurred_at` DATETIME NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `request_id` VARCHAR(80) NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_audit_logs_1` CHECK (LENGTH(TRIM(`action`)) > 0),
    CONSTRAINT `ck_audit_logs_2` CHECK (`old_values` IS NULL OR JSON_VALID(`old_values`)),
    CONSTRAINT `ck_audit_logs_3` CHECK (`new_values` IS NULL OR JSON_VALID(`new_values`)),
    CONSTRAINT `fk_audit_logs_6ec16f9c` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_audit_logs_da9eab2f` ON `audit_logs` (`entity_type`, `entity_id`)');
        DB::statement('CREATE INDEX `ix_audit_logs_92da6dde` ON `audit_logs` (`action`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_audit_logs_5fe56ab1` ON `audit_logs` (`user_id`, `occurred_at`)');
        DB::statement('CREATE INDEX `ix_audit_logs_cace4a15` ON `audit_logs` (`user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('backup_restores');
        Schema::dropIfExists('backup_histories');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('print_templates');
    }
};
