<?php

namespace App\Support;

use App\Models\Contract;
use Illuminate\Support\Carbon;

/**
 * Recurring invoice schedule: due dates and generate-ahead (due_date - 7 days).
 */
final class ContractInvoiceSchedule
{
    public const GENERATE_DAYS_BEFORE_DUE = 7;

    public static function supportsRecurringGeneration(Contract $contract): bool
    {
        if ($contract->status !== Contract::STATUS_ACTIVE) {
            return false;
        }

        if ($contract->type === 'rent') {
            return $contract->start_date !== null;
        }

        if ($contract->type === 'sale') {
            return $contract->payment_type === 'installment'
                && (int) ($contract->duration_months ?? 0) > 0
                && $contract->start_date !== null;
        }

        return false;
    }

    /**
     * Day-of-month used for recurring due dates (from contract start date).
     */
    public static function recurringDueDay(Contract $contract): int
    {
        $start = Carbon::parse($contract->start_date);

        return min(max((int) $start->day, 1), 28);
    }

    public static function dueDateInMonth(Carbon $month, int $dueDay): Carbon
    {
        $monthStart = $month->copy()->startOfMonth();
        $day = min(max($dueDay, 1), $monthStart->daysInMonth);

        return $monthStart->copy()->day($day)->startOfDay();
    }

    public static function generateDateForDueDate(Carbon $dueDate): Carbon
    {
        return $dueDate->copy()->subDays(self::GENERATE_DAYS_BEFORE_DUE)->startOfDay();
    }

    /**
     * Billing periods that should auto-generate as of $asOf (inclusive).
     *
     * @return list<array{billing_month: Carbon, due_date: Carbon, generate_date: Carbon}>
     */
    public static function periodsDueForGeneration(Contract $contract, Carbon $asOf): array
    {
        if (! self::supportsRecurringGeneration($contract)) {
            return [];
        }

        $asOf = $asOf->copy()->startOfDay();
        $start = Carbon::parse($contract->start_date)->startOfDay();

        if ($contract->type === 'rent') {
            return self::rentPeriodsDueForGeneration($contract, $start, $asOf);
        }

        return self::saleInstallmentPeriodsDueForGeneration($contract, $start, $asOf);
    }

    /**
     * @return list<array{billing_month: Carbon, due_date: Carbon, generate_date: Carbon}>
     */
    private static function rentPeriodsDueForGeneration(Contract $contract, Carbon $start, Carbon $asOf): array
    {
        $dueDay = self::recurringDueDay($contract);
        $end = $contract->end_date
            ? Carbon::parse($contract->end_date)->startOfDay()
            : null;

        $periods = [];
        // First recurring due is one month after the contract start date.
        $cursor = $start->copy()->addMonth()->startOfMonth();
        $safety = 0;

        while ($safety < 600) {
            $safety++;
            $dueDate = self::dueDateInMonth($cursor, $dueDay);

            if ($end && $dueDate->gt($end)) {
                break;
            }

            $generateDate = self::generateDateForDueDate($dueDate);

            if ($generateDate->gt($asOf)) {
                break;
            }

            $periods[] = [
                'billing_month' => $dueDate->copy()->startOfMonth(),
                'due_date' => $dueDate,
                'generate_date' => $generateDate,
            ];

            $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * @return list<array{billing_month: Carbon, due_date: Carbon, generate_date: Carbon}>
     */
    private static function saleInstallmentPeriodsDueForGeneration(Contract $contract, Carbon $start, Carbon $asOf): array
    {
        $dueDay = self::recurringDueDay($contract);
        $months = max((int) $contract->duration_months, 1);
        $periods = [];

        for ($index = 1; $index <= $months; $index++) {
            $anchor = $start->copy()->addMonths($index)->startOfMonth();
            $dueDate = self::dueDateInMonth($anchor, $dueDay);
            $generateDate = self::generateDateForDueDate($dueDate);

            if ($generateDate->gt($asOf)) {
                break;
            }

            if ($asOf->lt($start)) {
                continue;
            }

            $periods[] = [
                'billing_month' => $dueDate->copy()->startOfMonth(),
                'due_date' => $dueDate,
                'generate_date' => $generateDate,
            ];
        }

        return $periods;
    }
}
