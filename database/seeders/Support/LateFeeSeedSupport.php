<?php

namespace Database\Seeders\Support;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Support\InvoiceLateFeePolicy;
use Illuminate\Support\Carbon;

/**
 * Deterministic Late Fee rule seeding + per-invoice policy snapshots for demo data.
 *
 * Pending/draft invoices stay unselected. Issued+ invoices get a locked snapshot.
 * Accrued late_fee amounts use InvoiceLateFeePolicy::calculateAmount (same as runtime).
 */
final class LateFeeSeedSupport
{
    public const RULE_STANDARD = 'Standard Residential Late Fee';

    public const RULE_EXTENDED = 'Extended Grace Late Fee';

    public const RULE_PERCENTAGE = 'Monthly Percentage Penalty';

    /** Legacy name from earlier demos — renamed in place by LateFeeSeeder. */
    public const LEGACY_STANDARD = 'Standard Late Fee';

    /** Higher-value residential threshold (MMK) for Extended Grace assignment. */
    public const HIGH_VALUE_THRESHOLD = 600_000.0;

    /**
     * @return array{
     *     late_fee_rule_id: int|null,
     *     late_fee_waived: bool,
     *     late_fee_policy_name: string|null,
     *     late_fee_policy_type: string|null,
     *     late_fee_policy_value: float|null,
     *     late_fee_policy_per: string|null,
     *     late_fee_policy_grace_days: int|null,
     *     late_fee_policy_locked: bool,
     *     late_fee: float
     * }
     */
    public static function attributesForSeedInvoice(
        string $status,
        string $type,
        float $subtotal,
        string $invoiceNumber,
        ?Carbon $dueDate,
        ?Carbon $asOf = null,
    ): array {
        if ($status === Invoice::STATUS_DRAFT || $status === Invoice::STATUS_CANCELLED) {
            return [
                'late_fee_rule_id' => null,
                'late_fee_waived' => false,
                'late_fee_policy_name' => null,
                'late_fee_policy_type' => null,
                'late_fee_policy_value' => null,
                'late_fee_policy_per' => null,
                'late_fee_policy_grace_days' => null,
                'late_fee_policy_locked' => false,
                'late_fee' => 0.0,
            ];
        }

        $rule = self::resolveRule($type, $subtotal, $invoiceNumber);
        $snapshot = InvoiceLateFeePolicy::snapshotFromRule($rule, locked: true);
        $asOf ??= Carbon::now()->startOfDay();

        $probe = new Invoice([
            'due_date' => $dueDate,
            'total_amount' => $subtotal,
            'late_fee' => 0,
            // Accrue for issued/partial/overdue; paid uses stored late_fee (0 until set).
            'status' => $status === Invoice::STATUS_PAID
                ? Invoice::STATUS_OVERDUE
                : $status,
            ...$snapshot,
        ]);

        $amount = InvoiceLateFeePolicy::calculateAmount($probe, $asOf);

        // Paid invoices keep a locked snapshot but only retain fee if it would
        // already have accrued by settlement time (seed treats paid as settled
        // after the demo as-of accrual window).
        if ($status === Invoice::STATUS_PAID) {
            // Historical paid rows in this demo are treated as settled without
            // carrying an open overdue balance — keep snapshot, fee = 0.
            $amount = 0.0;
        }

        return [
            ...$snapshot,
            'late_fee' => round($amount, 2),
        ];
    }

    public static function resolveRule(string $type, float $subtotal, string $invoiceNumber): LateFee
    {
        if ($type === 'sale') {
            return self::ruleByName(self::RULE_PERCENTAGE);
        }

        $bucket = crc32($invoiceNumber) & 0x7fffffff;

        if ($subtotal >= self::HIGH_VALUE_THRESHOLD && ($bucket % 4) === 0) {
            return self::ruleByName(self::RULE_EXTENDED);
        }

        return self::ruleByName(self::RULE_STANDARD);
    }

    public static function ruleByName(string $name): LateFee
    {
        $rule = LateFee::query()->where('name', $name)->first();

        if ($rule) {
            return $rule;
        }

        // Self-heal if LateFeeSeeder has not run yet in this process.
        (new \Database\Seeders\LateFeeSeeder)->run();

        return LateFee::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Idempotent backfill for issued+ invoices missing a locked policy snapshot.
     */
    public static function normalizeHistoricalInvoicePolicies(?Carbon $asOf = null): int
    {
        $asOf ??= Carbon::now()->startOfDay();
        $updated = 0;

        Invoice::query()
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
                Invoice::STATUS_PAID,
            ])
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($asOf, &$updated): void {
                $attrs = self::attributesForSeedInvoice(
                    (string) $invoice->status,
                    (string) $invoice->type,
                    (float) $invoice->total_amount,
                    (string) $invoice->invoice_number,
                    $invoice->due_date,
                    $asOf,
                );

                $invoice->fill($attrs);
                if ($invoice->isDirty()) {
                    $invoice->save();
                    $updated++;
                }
            });

        // Draft/pending approval: ensure no preselected rule.
        Invoice::query()
            ->where('status', Invoice::STATUS_DRAFT)
            ->where(function ($query): void {
                $query->whereNotNull('late_fee_rule_id')
                    ->orWhere('late_fee_policy_locked', true)
                    ->orWhere('late_fee', '>', 0);
            })
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$updated): void {
                $invoice->update([
                    'late_fee_rule_id' => null,
                    'late_fee_waived' => false,
                    'late_fee_policy_name' => null,
                    'late_fee_policy_type' => null,
                    'late_fee_policy_value' => null,
                    'late_fee_policy_per' => null,
                    'late_fee_policy_grace_days' => null,
                    'late_fee_policy_locked' => false,
                    'late_fee' => 0,
                ]);
                $updated++;
            });

        return $updated;
    }
}
