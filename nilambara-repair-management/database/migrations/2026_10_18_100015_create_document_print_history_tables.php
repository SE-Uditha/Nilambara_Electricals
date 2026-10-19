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
CREATE TABLE `document_print_histories` (
    `id` {$id},
    `print_template_id` BIGINT UNSIGNED NULL,
    `repair_receipt_id` BIGINT UNSIGNED NULL,
    `job_card_id` BIGINT UNSIGNED NULL,
    `quotation_id` BIGINT UNSIGNED NULL,
    `invoice_id` BIGINT UNSIGNED NULL,
    `pos_sale_id` BIGINT UNSIGNED NULL,
    `payment_id` BIGINT UNSIGNED NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `purchase_id` BIGINT UNSIGNED NULL,
    `printed_at` DATETIME NOT NULL,
    `printed_by_user_id` BIGINT UNSIGNED NOT NULL,
    `is_duplicate` BOOLEAN NOT NULL DEFAULT 0,
    `duplicate_label` VARCHAR(100) NULL,
    `document_snapshot` JSON NOT NULL,
    `output_reference` VARCHAR(255) NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    CONSTRAINT `ck_document_print_histories_1` CHECK ((CASE WHEN `repair_receipt_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `job_card_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `quotation_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `invoice_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `pos_sale_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `payment_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `customer_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `purchase_id` IS NOT NULL THEN 1 ELSE 0 END) = 1),
    CONSTRAINT `ck_document_print_histories_2` CHECK (`document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)),
    CONSTRAINT `fk_document_print_histories_c5fd8b20` FOREIGN KEY (`print_template_id`) REFERENCES `print_templates` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_13ea5f1f` FOREIGN KEY (`repair_receipt_id`) REFERENCES `repair_receipts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_b9ffa3b0` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_ae50c22d` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_057bbe69` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_3b08c716` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_178f8cd3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_fe84d401` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_8af16fdf` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_document_print_histories_28ec020d` FOREIGN KEY (`printed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
){$suffix}
SQL);
        DB::statement('CREATE INDEX `ix_document_print_histories_52a2214d` ON `document_print_histories` (`print_template_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_66747a19` ON `document_print_histories` (`repair_receipt_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_cf053e5b` ON `document_print_histories` (`job_card_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_596c572d` ON `document_print_histories` (`quotation_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_15d25c20` ON `document_print_histories` (`invoice_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_bc1ec442` ON `document_print_histories` (`pos_sale_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_fcaec7df` ON `document_print_histories` (`payment_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_a7a13f4c` ON `document_print_histories` (`customer_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_86cc2ebe` ON `document_print_histories` (`purchase_id`)');
        DB::statement('CREATE INDEX `ix_document_print_histories_432b819f` ON `document_print_histories` (`printed_by_user_id`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_print_histories');
    }
};
