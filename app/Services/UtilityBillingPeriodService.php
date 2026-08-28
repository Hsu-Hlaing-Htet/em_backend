<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Utility;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Contract-scoped utility billing period rules:
 * - billing_month = usage month
 * - reading_date = meter reading date (typically 1st of next month)
 * - no skipped months within a contract
 * - no records before contract start / after contract end
 */
class UtilityBillingPeriodService
{
    /**
     * Resolve the contract that owns utility billing for a room + billing month.
     */
    public function resolveContractForRoomMonth(int $roomId, Carbon $billingMonth): Contract
    {
        $monthStart = $billingMonth->copy()->startOfMonth();
        $monthEnd = $billingMonth->copy()->endOfMonth();

        $inPeriod = Contract::query()
            ->where('room_id', $roomId)
            ->whereDate('start_date', '<=', $monthEnd)
            ->where(function ($query) use ($monthStart) {
                $query->where(function ($rent) use ($monthStart) {
                    $rent->where('type', 'rent')
                        ->whereIn('status', ['active', 'completed'])
                        ->where(function ($period) use ($monthStart) {
                            $period->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $monthStart);
                        });
                })->orWhere(function ($sale) {
                    $sale->where('type', 'sale')
                        ->whereIn('status', ['approved', 'active', 'completed']);
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($inPeriod) {
            return $inPeriod;
        }

        // Fall back so period validation can explain "after contract end".
        $contract = Contract::query()
            ->where('room_id', $roomId)
            ->whereDate('start_date', '<=', $monthEnd)
            ->where(function ($query) {
                $query->where(function ($rent) {
                    $rent->where('type', 'rent')
                        ->whereIn('status', ['active', 'completed']);
                })->orWhere(function ($sale) {
                    $sale->where('type', 'sale')
                        ->whereIn('status', ['approved', 'active', 'completed']);
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (! $contract) {
            throw ValidationException::withMessages([
                'room_id' => ['No active contract found for this room in the selected billing month.'],
            ]);
        }

        return $contract;
    }

    public function defaultReadingDate(Carbon $billingMonth): Carbon
    {
        return $billingMonth->copy()->startOfMonth()->addMonth()->startOfMonth();
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreate(
        Contract $contract,
        Carbon $billingMonth,
        ?Carbon $readingDate = null,
        ?int $ignoreUtilityId = null,
        ?Carbon $originalBillingMonth = null,
    ): void {
        $billingMonth = $billingMonth->copy()->startOfMonth();
        $readingDate = ($readingDate ?? $this->defaultReadingDate($billingMonth))->copy()->startOfDay();

        $this->assertRoomMatchesContract($contract);
        $this->assertWithinContractPeriod($contract, $billingMonth);
        $this->assertNoDuplicate($contract, $billingMonth, $ignoreUtilityId);
        $this->assertMonthlySequence($contract, $billingMonth, $ignoreUtilityId, $originalBillingMonth);
        $this->assertReadingDate($billingMonth, $readingDate);
    }

    /**
     * @throws ValidationException
     */
    public function assertCanCreateAfterBillingMonth(
        Contract $contract,
        Carbon $billingMonth,
        Carbon $lastBillingMonth,
        ?Carbon $readingDate = null,
    ): void {
        $billingMonth = $billingMonth->copy()->startOfMonth();
        $lastBillingMonth = $lastBillingMonth->copy()->startOfMonth();
        $readingDate = ($readingDate ?? $this->defaultReadingDate($billingMonth))->copy()->startOfDay();

        $this->assertRoomMatchesContract($contract);
        $this->assertWithinContractPeriod($contract, $billingMonth);
        $this->assertNoDuplicate($contract, $billingMonth, null);

        $expectedMonth = $lastBillingMonth->copy()->addMonth()->startOfMonth();

        if ($billingMonth->lte($lastBillingMonth)) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'A utility record already exists for %s on this contract.',
                        $billingMonth->format('F Y'),
                    ),
                ],
            ]);
        }

        if (! $billingMonth->equalTo($expectedMonth)) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'Please enter the %s utility record before adding %s.',
                        $expectedMonth->format('F'),
                        $billingMonth->format('F'),
                    ),
                ],
            ]);
        }

        $this->assertReadingDate($billingMonth, $readingDate);
    }

    private function assertRoomMatchesContract(Contract $contract): void
    {
        if (! $contract->room_id) {
            throw ValidationException::withMessages([
                'room_id' => ['Selected contract is not linked to a room.'],
            ]);
        }
    }

    private function assertWithinContractPeriod(Contract $contract, Carbon $billingMonth): void
    {
        $contractStartMonth = Carbon::parse($contract->start_date)->startOfMonth();

        if ($billingMonth->lt($contractStartMonth)) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'Utility billing cannot start before the contract start month (%s).',
                        $contractStartMonth->format('F Y'),
                    ),
                ],
            ]);
        }

        if ($contract->type === 'rent' && $contract->end_date) {
            $contractEndMonth = Carbon::parse($contract->end_date)->startOfMonth();

            if ($billingMonth->gt($contractEndMonth)) {
                throw ValidationException::withMessages([
                    'billing_month' => [
                        sprintf(
                            'Utility billing cannot continue after the contract end month (%s).',
                            $contractEndMonth->format('F Y'),
                        ),
                    ],
                ]);
            }
        }
    }

    private function assertNoDuplicate(Contract $contract, Carbon $billingMonth, ?int $ignoreUtilityId): void
    {
        $exists = Utility::query()
            ->where('contract_id', $contract->id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->when($ignoreUtilityId, fn ($query) => $query->whereKeyNot($ignoreUtilityId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'A utility record already exists for %s on this contract.',
                        $billingMonth->format('F Y'),
                    ),
                ],
            ]);
        }
    }

    private function assertMonthlySequence(
        Contract $contract,
        Carbon $billingMonth,
        ?int $ignoreUtilityId,
        ?Carbon $originalBillingMonth = null,
    ): void {
        if (
            $ignoreUtilityId
            && $originalBillingMonth
            && $billingMonth->equalTo($originalBillingMonth->copy()->startOfMonth())
        ) {
            return;
        }

        $latest = Utility::query()
            ->where('contract_id', $contract->id)
            ->when($ignoreUtilityId, fn ($query) => $query->whereKeyNot($ignoreUtilityId))
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->first();

        $contractStartMonth = Carbon::parse($contract->start_date)->startOfMonth();

        if (! $latest) {
            if (! $billingMonth->equalTo($contractStartMonth)) {
                throw ValidationException::withMessages([
                    'billing_month' => [
                        sprintf(
                            'Please enter the %s utility record first for this contract.',
                            $contractStartMonth->format('F Y'),
                        ),
                    ],
                ]);
            }

            return;
        }

        $latestMonth = Carbon::parse($latest->billing_month)->startOfMonth();
        $expectedMonth = $latestMonth->copy()->addMonth()->startOfMonth();

        if ($billingMonth->lte($latestMonth)) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'A utility record already exists for %s on this contract.',
                        $billingMonth->format('F Y'),
                    ),
                ],
            ]);
        }

        if (! $billingMonth->equalTo($expectedMonth)) {
            throw ValidationException::withMessages([
                'billing_month' => [
                    sprintf(
                        'Please enter the %s utility record before adding %s.',
                        $expectedMonth->format('F'),
                        $billingMonth->format('F'),
                    ),
                ],
            ]);
        }
    }

    private function assertReadingDate(Carbon $billingMonth, Carbon $readingDate): void
    {
        $earliest = $billingMonth->copy()->startOfMonth();
        $latest = $billingMonth->copy()->addMonths(2)->endOfMonth();

        if ($readingDate->lt($earliest) || $readingDate->gt($latest)) {
            throw ValidationException::withMessages([
                'reading_date' => [
                    'Reading date must fall within the billing month or the following month.',
                ],
            ]);
        }
    }
}
