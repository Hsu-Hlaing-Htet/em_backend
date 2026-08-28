<?php

use App\Models\Contract;
use App\Models\Utility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->foreignId('contract_id')
                ->nullable()
                ->after('room_id')
                ->constrained('contracts')
                ->nullOnDelete();
            $table->date('reading_date')
                ->nullable()
                ->after('billing_month');
        });

        $this->backfillContractAndReadingDate();

        Schema::table('utilities', function (Blueprint $table) {
            $table->unique(['contract_id', 'billing_month'], 'utilities_contract_id_billing_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropUnique('utilities_contract_id_billing_month_unique');
            $table->dropConstrainedForeignId('contract_id');
            $table->dropColumn('reading_date');
        });
    }

    private function backfillContractAndReadingDate(): void
    {
        Utility::query()
            ->orderBy('id')
            ->each(function (Utility $utility): void {
                $billingMonth = Carbon::parse($utility->billing_month)->startOfMonth();
                $readingDate = $billingMonth->copy()->addMonth()->startOfMonth();

                $contractId = $this->resolveHistoricalContractId((int) $utility->room_id, $billingMonth);

                DB::table('utilities')
                    ->where('id', $utility->id)
                    ->update([
                        'contract_id' => $contractId,
                        'reading_date' => $readingDate->toDateString(),
                    ]);
            });
    }

    private function resolveHistoricalContractId(int $roomId, Carbon $billingMonth): ?int
    {
        $monthStart = $billingMonth->copy()->startOfMonth();
        $monthEnd = $billingMonth->copy()->endOfMonth();

        $contract = Contract::query()
            ->where('room_id', $roomId)
            ->whereDate('start_date', '<=', $monthEnd)
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $monthStart);
            })
            ->where(function ($query) {
                $query->where(function ($rent) {
                    $rent->where('type', 'rent')
                        ->whereIn('status', ['active', 'completed', 'approved', 'pending']);
                })->orWhere(function ($sale) {
                    $sale->where('type', 'sale')
                        ->whereIn('status', ['approved', 'active', 'completed', 'pending']);
                });
            })
            ->orderByDesc('id')
            ->first();

        return $contract?->id;
    }
};
