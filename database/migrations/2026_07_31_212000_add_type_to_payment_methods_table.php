<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('type')->nullable()->after('slug');
        });

        $typeBySlug = [
            'cash' => 'cash',
            'kbz-bank-transfer' => 'bank_transfer',
            'aya-bank-transfer' => 'bank_transfer',
            'kbz-pay' => 'mobile_wallet',
            'wave-pay' => 'mobile_wallet',
            'cheque' => 'cheque',
        ];

        foreach ($typeBySlug as $slug => $type) {
            DB::table('payment_methods')
                ->where('slug', $slug)
                ->whereNull('type')
                ->update(['type' => $type, 'updated_at' => now()]);
        }

        DB::table('payment_methods')
            ->whereNull('type')
            ->update(['type' => 'other', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
