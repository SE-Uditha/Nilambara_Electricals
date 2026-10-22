<?php

use App\Modules\RolesPermissions\Services\MergeTechnicianIntoCashier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MergeTechnicianIntoCashier::class)->run();
    }

    public function down(): void
    {
        // The audit log preserves the original split; never guess or revoke later grants.
    }
};
