<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\LateFee;
use Illuminate\Support\Carbon;

class InvoiceLateFeePolicy
{
    public const SELECTION_NONE = 'none';

    public const MODE_NONE = 'none';

    public const MODE_RULE = 'rule';

    /**
     * @return array{
     *     mode: string|null,
     *     selection: int|string|null,
     *     rule_id: int|null,
     *     waived: bool,
     *     locked: bool,
     *     name: string|null,
     *     type: string|null,
     *     value: float|null,
     *     per: string|null,
     *     grace_days: int|null,
     *     label: string|null,
     *     summary: string|null
     * }
     */
    public static function fromInvoice(Invoice $invoice): array
    {
        $waived = (bool) $invoice->late_fee_waived;
        $ruleId = $invoice->late_fee_rule_id ? (int) $invoice->late_fee_rule_id : null;
        $locked = (bool) $invoice->late_fee_policy_locked;
        $type = $invoice->late_fee_policy_type;

        $mode = null;
        $selection = null;

        if ($waived || $type === self::MODE_NONE) {
            $mode = self::MODE_NONE;
            $selection = self::SELECTION_NONE;
        } elseif ($ruleId !== null || ($type !== null && $type !== self::MODE_NONE)) {
            $mode = self::MODE_RULE;
            $selection = $ruleId;
        }

        $name = $invoice->late_fee_policy_name;
        $value = $invoice->late_fee_policy_value !== null
            ? (float) $invoice->late_fee_policy_value
            : null;
        $per = $invoice->late_fee_policy_per;
        $graceDays = $invoice->late_fee_policy_grace_days !== null
            ? (int) $invoice->late_fee_policy_grace_days
            : null;

        $label = self::formatLabel($mode, $name, $type, $value, $per, $graceDays);
        $summary = self::formatSummary($mode, $name, $type, $value, $per, $graceDays);

        return [
            'mode' => $mode,
            'selection' => $selection,
            'rule_id' => $ruleId,
            'waived' => $waived || $mode === self::MODE_NONE,
            'locked' => $locked,
            'name' => $mode === self::MODE_NONE ? 'No Late Fee' : $name,
            'type' => $type,
            'value' => $value,
            'per' => $per,
            'grace_days' => $graceDays,
            'label' => $label,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *     late_fee_rule_id: int|null,
     *     late_fee_waived: bool,
     *     late_fee_policy_name: string|null,
     *     late_fee_policy_type: string|null,
     *     late_fee_policy_value: float|null,
     *     late_fee_policy_per: string|null,
     *     late_fee_policy_grace_days: int|null,
     *     late_fee_policy_locked: bool
     * }
     */
    public static function snapshotNone(bool $locked = false): array
    {
        return [
            'late_fee_rule_id' => null,
            'late_fee_waived' => true,
            'late_fee_policy_name' => 'No Late Fee',
            'late_fee_policy_type' => self::MODE_NONE,
            'late_fee_policy_value' => null,
            'late_fee_policy_per' => null,
            'late_fee_policy_grace_days' => null,
            'late_fee_policy_locked' => $locked,
        ];
    }

    /**
     * @return array{
     *     late_fee_rule_id: int,
     *     late_fee_waived: bool,
     *     late_fee_policy_name: string,
     *     late_fee_policy_type: string,
     *     late_fee_policy_value: float,
     *     late_fee_policy_per: string,
     *     late_fee_policy_grace_days: int,
     *     late_fee_policy_locked: bool
     * }
     */
    public static function snapshotFromRule(LateFee $rule, bool $locked = false): array
    {
        return [
            'late_fee_rule_id' => (int) $rule->id,
            'late_fee_waived' => false,
            'late_fee_policy_name' => (string) $rule->name,
            'late_fee_policy_type' => (string) $rule->type,
            'late_fee_policy_value' => (float) $rule->value,
            'late_fee_policy_per' => (string) $rule->per,
            'late_fee_policy_grace_days' => (int) $rule->grace_days,
            'late_fee_policy_locked' => $locked,
        ];
    }

    public static function formatOptionLabel(LateFee $rule): string
    {
        $rate = self::formatRate((string) $rule->type, (float) $rule->value, (string) $rule->per);
        $grace = (int) $rule->grace_days;

        return trim(sprintf(
            '%s — %s · %d grace day%s',
            $rule->name,
            $rate,
            $grace,
            $grace === 1 ? '' : 's',
        ));
    }

    public static function formatLabel(
        ?string $mode,
        ?string $name,
        ?string $type,
        ?float $value,
        ?string $per,
        ?int $graceDays,
    ): ?string {
        if ($mode === null) {
            return null;
        }

        if ($mode === self::MODE_NONE) {
            return 'No Late Fee';
        }

        if ($name === null || $type === null || $value === null || $per === null) {
            return $name;
        }

        $rate = self::formatRate($type, $value, $per);
        $grace = $graceDays ?? 0;

        return sprintf(
            '%s — %s after %d grace day%s',
            $name,
            $rate,
            $grace,
            $grace === 1 ? '' : 's',
        );
    }

    public static function formatSummary(
        ?string $mode,
        ?string $name,
        ?string $type,
        ?float $value,
        ?string $per,
        ?int $graceDays,
    ): ?string {
        $label = self::formatLabel($mode, $name, $type, $value, $per, $graceDays);

        return $label ? 'LATE FEE POLICY: '.$label : null;
    }

    public static function formatRate(string $type, float $value, string $per): string
    {
        if ($type === LateFee::TYPE_PERCENTAGE) {
            $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

            return sprintf('%s%% / %s', $formatted, $per);
        }

        return sprintf('MMK %s / %s', number_format($value, 0, '.', ','), $per);
    }

    /**
     * Document NOTES rule line: "Name — MMK 5,000 per day · 1 grace day"
     */
    public static function formatDocumentRuleLabel(
        ?string $mode,
        ?string $name,
        ?string $type,
        ?float $value,
        ?string $per,
        ?int $graceDays,
    ): ?string {
        if ($mode === null || $mode === self::MODE_NONE) {
            return null;
        }

        if ($name === null || $type === null || $value === null || $per === null) {
            return $name;
        }

        $rate = self::formatDocumentRate($type, $value, $per);
        $grace = $graceDays ?? 0;

        return sprintf(
            '%s — %s · %d grace day%s',
            $name,
            $rate,
            $grace,
            $grace === 1 ? '' : 's',
        );
    }

    public static function formatDocumentRate(string $type, float $value, string $per): string
    {
        $period = match ($per) {
            LateFee::PER_MONTH => 'month',
            default => 'day',
        };

        if ($type === LateFee::TYPE_PERCENTAGE) {
            $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

            return sprintf('%s%% per %s', $formatted, $period);
        }

        return sprintf('MMK %s per %s', number_format($value, 0, '.', ','), $period);
    }

    /**
     * Canonical NOTES Late Fee block for Preview / Print / PDF.
     *
     * @return array{rule: string, calculation: list<string>|null}|null
     */
    public static function documentNotes(Invoice $invoice, ?Carbon $asOf = null): ?array
    {
        $policy = self::fromInvoice($invoice);

        if ($policy['mode'] !== self::MODE_RULE) {
            return null;
        }

        $rule = self::formatDocumentRuleLabel(
            $policy['mode'],
            $policy['name'],
            $policy['type'],
            $policy['value'],
            $policy['per'],
            $policy['grace_days'],
        );

        if ($rule === null) {
            return null;
        }

        $lateFee = round((float) ($invoice->late_fee ?? 0), 2);

        if ($lateFee <= 0) {
            return [
                'rule' => $rule,
                'calculation' => null,
            ];
        }

        return [
            'rule' => $rule,
            'calculation' => self::documentCalculationLines($invoice, $policy, $lateFee, $asOf),
        ];
    }

    /**
     * Receipt supporting notes: only when the invoice actually charged a Late Fee.
     *
     * @return array{rule: string, calculation: list<string>}|null
     */
    public static function receiptDocumentNotes(Invoice $invoice, ?Carbon $asOf = null): ?array
    {
        if (round((float) ($invoice->late_fee ?? 0), 2) <= 0) {
            return null;
        }

        $notes = self::documentNotes($invoice, $asOf);

        if ($notes === null || empty($notes['calculation'])) {
            return null;
        }

        return [
            'rule' => $notes['rule'],
            'calculation' => $notes['calculation'],
        ];
    }

    /**
     * @param  array{
     *     type: string|null,
     *     value: float|null,
     *     per: string|null,
     *     grace_days: int|null
     * }  $policy
     * @return list<string>
     */
    private static function documentCalculationLines(
        Invoice $invoice,
        array $policy,
        float $lateFee,
        ?Carbon $asOf = null,
    ): array {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $graceDays = (int) ($policy['grace_days'] ?? 0);
        $overdueDays = self::daysPastDue($invoice, $asOf);
        $chargeableDays = self::chargeableDays($invoice, $asOf);
        $type = (string) ($policy['type'] ?? '');
        $per = (string) ($policy['per'] ?? '');
        $value = (float) ($policy['value'] ?? 0);
        $subtotal = (float) $invoice->total_amount;

        $lines = [];

        if ($overdueDays > 0 && $chargeableDays > 0) {
            $lines[] = sprintf(
                '%d overdue day%s − %d grace day%s = %d chargeable day%s',
                $overdueDays,
                $overdueDays === 1 ? '' : 's',
                $graceDays,
                $graceDays === 1 ? '' : 's',
                $chargeableDays,
                $chargeableDays === 1 ? '' : 's',
            );
        }

        if ($per === LateFee::PER_MONTH) {
            $periods = (int) max(1, (int) ceil(max($chargeableDays, 1) / 30));
        } else {
            $periods = max($chargeableDays, 1);
        }

        if ($type === LateFee::TYPE_PERCENTAGE) {
            $pct = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            $lines[] = 'Base amount: '.self::formatDocumentMoney($subtotal);

            if ($periods === 1) {
                $lines[] = sprintf(
                    '%s%% × %s = %s',
                    $pct,
                    self::formatDocumentMoney($subtotal),
                    self::formatDocumentMoney($lateFee),
                );
            } else {
                $periodLabel = $per === LateFee::PER_MONTH ? 'months' : 'days';
                $lines[] = sprintf(
                    '%s%% × %s × %d %s = %s',
                    $pct,
                    self::formatDocumentMoney($subtotal),
                    $periods,
                    $periodLabel,
                    self::formatDocumentMoney($lateFee),
                );
            }

            $lines[] = 'Late Fee: '.self::formatDocumentMoney($lateFee);

            return $lines;
        }

        // Fixed amount rules
        if ($per === LateFee::PER_MONTH) {
            $lines[] = sprintf(
                '%d month%s × %s = %s',
                $periods,
                $periods === 1 ? '' : 's',
                self::formatDocumentMoney($value),
                self::formatDocumentMoney($lateFee),
            );
        } else {
            $lines[] = sprintf(
                '%d day%s × %s = %s',
                $periods,
                $periods === 1 ? '' : 's',
                self::formatDocumentMoney($value),
                self::formatDocumentMoney($lateFee),
            );
        }

        return $lines;
    }

    public static function daysPastDue(Invoice $invoice, ?Carbon $asOf = null): int
    {
        if (! $invoice->due_date) {
            return 0;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $due = $invoice->due_date->copy()->startOfDay();
        $days = (int) $due->diffInDays($asOf, false);

        return max(0, $days);
    }

    public static function formatDocumentMoney(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.001 ? 0 : 2;

        return 'MMK '.number_format($amount, $decimals, '.', ',');
    }

    /**
     * Chargeable days after due date + grace period.
     * Due date itself and the following grace_days do not accrue fees.
     */
    public static function chargeableDays(Invoice $invoice, ?Carbon $asOf = null): int
    {
        if (! $invoice->due_date) {
            return 0;
        }

        $policy = self::fromInvoice($invoice);

        if ($policy['mode'] !== self::MODE_RULE) {
            return 0;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $due = $invoice->due_date->copy()->startOfDay();
        $graceDays = (int) ($policy['grace_days'] ?? 0);
        $daysPastDue = (int) $due->diffInDays($asOf, false);

        if ($daysPastDue <= $graceDays) {
            return 0;
        }

        return $daysPastDue - $graceDays;
    }

    public static function calculateAmount(Invoice $invoice, ?Carbon $asOf = null): float
    {
        $policy = self::fromInvoice($invoice);

        if ($policy['mode'] !== self::MODE_RULE) {
            return 0.0;
        }

        if (in_array($invoice->status, [
            Invoice::STATUS_PAID,
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_DRAFT,
        ], true)) {
            return (float) ($invoice->late_fee ?? 0);
        }

        $chargeableDays = self::chargeableDays($invoice, $asOf);

        if ($chargeableDays <= 0) {
            return 0.0;
        }

        $subtotal = (float) $invoice->total_amount;
        $value = (float) ($policy['value'] ?? 0);
        $type = (string) ($policy['type'] ?? '');
        $per = (string) ($policy['per'] ?? '');

        if ($per === LateFee::PER_MONTH) {
            $periods = (int) max(1, (int) ceil($chargeableDays / 30));
        } else {
            $periods = $chargeableDays;
        }

        if ($type === LateFee::TYPE_PERCENTAGE) {
            return round($subtotal * ($value / 100) * $periods, 2);
        }

        return round($value * $periods, 2);
    }
}
