<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        DB::statement(<<<SQL
CREATE TABLE repair_job_charges (
    id {$id},
    repair_job_id BIGINT UNSIGNED NOT NULL,
    line_number INTEGER NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(16,4) NOT NULL,
    line_total DECIMAL(18,2) NOT NULL,
    removed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_job_charges_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE RESTRICT,
    CONSTRAINT uq_job_charges_line UNIQUE (repair_job_id, line_number),
    CONSTRAINT ck_job_charges_line CHECK (line_number > 0),
    CONSTRAINT ck_job_charges_description CHECK (LENGTH(TRIM(description)) > 0),
    CONSTRAINT ck_job_charges_quantity CHECK (quantity > 0),
    CONSTRAINT ck_job_charges_price CHECK (unit_price >= 0),
    CONSTRAINT ck_job_charges_total CHECK (line_total >= 0 AND ROUND(quantity * unit_price, 2) = line_total)
){$suffix}
SQL);
        DB::statement('CREATE INDEX ix_job_charges_active ON repair_job_charges (repair_job_id, removed_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_job_charges');
    }
};
