<?php

use App\Modules\RolesPermissions\Services\MergeOperationsIntoCashier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MergeOperationsIntoCashier::class)->run();
    }

    public function down(): void
    {
        // The audit log preserves the original split; never guess or revoke later grants.
    }
};
