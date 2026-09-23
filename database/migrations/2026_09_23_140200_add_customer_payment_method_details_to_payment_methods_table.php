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
            $table->string('account_name')->nullable()->after('type');
            $table->string('account_number')->nullable()->after('account_name');
            $table->string('phone_number')->nullable()->after('account_number');
            $table->string('qr_image_path')->nullable()->after('phone_number');
            $table->text('instructions')->nullable()->after('qr_image_path');
            $table->boolean('is_customer_visible')->default(true)->after('status');
            $table->unsignedInteger('sort_order')->default(0)->after('is_customer_visible');
        });

        // Normalize legacy wallet type labels to the controlled "wallet" value.
        DB::table('payment_methods')
            ->whereIn('type', ['mobile_wallet', 'ewallet', 'e_wallet'])
            ->update(['type' => 'wallet', 'updated_at' => now()]);

        // Cash and legacy office methods are not customer-visible by default.
        DB::table('payment_methods')
            ->where(function ($query) {
                $query->whereIn('slug', ['cash', 'cheque', 'kbz-bank-transfer', 'aya-bank-transfer'])
                    ->orWhere('type', 'cash');
            })
            ->update(['is_customer_visible' => false, 'updated_at' => now()]);

        DB::table('payment_methods')
            ->where('type', 'wallet')
            ->update(['is_customer_visible' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'account_name',
                'account_number',
                'phone_number',
                'qr_image_path',
                'instructions',
                'is_customer_visible',
                'sort_order',
            ]);
        });
    }
};
