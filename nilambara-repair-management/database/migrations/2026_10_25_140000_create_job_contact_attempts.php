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
CREATE TABLE job_contact_attempts (
    id {$id},
    repair_job_id BIGINT UNSIGNED NOT NULL,
    attempt_number INTEGER NOT NULL,
    contacted_on DATE NOT NULL,
    notes VARCHAR(1000) NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_job_contact_attempts_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE RESTRICT,
    CONSTRAINT uq_job_contact_attempts_number UNIQUE (repair_job_id, attempt_number),
    CONSTRAINT ck_job_contact_attempts_number CHECK (attempt_number > 0)
){$suffix}
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('job_contact_attempts');
    }
};
