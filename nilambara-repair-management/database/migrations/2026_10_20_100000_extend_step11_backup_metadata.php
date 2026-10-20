<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_histories', function (Blueprint $table): void {
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->string('verification_status', 32)->default('not_verified');
            $table->string('cloud_file_id')->nullable();
            $table->string('cloud_status', 32)->default('not_configured');
            $table->text('destination_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('backup_histories', fn (Blueprint $table) => $table->dropColumn([
            'file_size', 'completed_at', 'deleted_at', 'is_protected', 'verification_status', 'cloud_file_id', 'cloud_status', 'destination_path',
        ]));
    }
};
