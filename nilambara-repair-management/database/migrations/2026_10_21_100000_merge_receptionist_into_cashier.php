<?php

use App\Modules\RolesPermissions\Services\MergeReceptionistIntoCashier;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(MergeReceptionistIntoCashier::class)->run();
    }

    public function down(): void
    {
        // Keep consolidated permissions/accounts: splitting them cannot be inferred safely.
        // The audit event retains the original role, grants and assignments for review.
    }
};
