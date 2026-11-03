<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = trim((string) config('business.customer_code_prefix', 'CUS-')) ?: 'CUS-';

        DB::table('customers')->whereRaw('LENGTH(customer_code) > 20')->orderBy('id')->each(function ($customer) use ($prefix): void {
            DB::table('customers')->where('id', $customer->id)->update([
                'customer_code' => $prefix.str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        // Previous long codes are not restorable.
    }
};
