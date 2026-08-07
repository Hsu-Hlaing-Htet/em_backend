<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'billing_month')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('billing_month')->nullable()->after('due_date');
            });
        }

        if (! Schema::hasColumn('utilities', 'invoice_id')) {
            Schema::table('utilities', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->after('total_amount')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'billing_month')) {
            $invoiceUtilityPairs = DB::table('invoices')
                ->whereNotNull('utility_id')
                ->whereNull('billing_month')
                ->get(['id', 'utility_id']);

            foreach ($invoiceUtilityPairs as $pair) {
                $billingMonth = DB::table('utilities')->where('id', $pair->utility_id)->value('billing_month');

                if ($billingMonth) {
                    DB::table('invoices')->where('id', $pair->id)->update(['billing_month' => $billingMonth]);
                }
            }

            DB::table('invoices')
                ->whereNull('billing_month')
                ->whereNotNull('issued_date')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('invoices')
                            ->where('id', $row->id)
                            ->update(['billing_month' => Carbon::parse($row->issued_date)->startOfMonth()->toDateString()]);
                    }
                });
        }

        if (Schema::hasTable('utilities') && Schema::hasColumn('utilities', 'invoice_id')) {
            $legacyLinks = DB::table('invoices')
                ->whereNotNull('utility_id')
                ->get(['id', 'utility_id']);

            foreach ($legacyLinks as $link) {
                DB::table('utilities')
                    ->where('id', $link->utility_id)
                    ->whereNull('invoice_id')
                    ->update(['invoice_id' => $link->id]);
            }
        }

        if (! $this->indexExists('invoices', 'invoices_contract_id_billing_month_unique')
            && ! $this->hasDuplicateContractBillingMonths()) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(['contract_id', 'billing_month'], 'invoices_contract_id_billing_month_unique');
            });
        }
    }

    private function hasDuplicateContractBillingMonths(): bool
    {
        if (! Schema::hasColumn('invoices', 'billing_month')) {
            return false;
        }

        return DB::table('invoices')
            ->whereNotNull('contract_id')
            ->whereNotNull('billing_month')
            ->select('contract_id', 'billing_month')
            ->groupBy('contract_id', 'billing_month')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    public function down(): void
    {
        if ($this->indexExists('invoices', 'invoices_contract_id_billing_month_unique')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_contract_id_billing_month_unique');
            });
        }

        if (Schema::hasColumn('utilities', 'invoice_id')) {
            Schema::table('utilities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }

        if (Schema::hasColumn('invoices', 'billing_month')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('billing_month');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => $index->name === $indexName);
        }

        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName],
        );

        return (int) ($result[0]->aggregate ?? 0) > 0;
    }
};
