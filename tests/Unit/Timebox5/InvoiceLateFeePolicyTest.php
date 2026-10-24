<?php

use App\Models\Invoice;
use App\Models\LateFee;
use App\Support\InvoiceLateFeePolicy;
use Illuminate\Support\Carbon;

test('fixed late fees apply only after the configured grace period', function () {
    $invoice = new Invoice([
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 250000,
        'due_date' => '2026-03-01',
        'late_fee_policy_type' => LateFee::TYPE_FIXED,
        'late_fee_policy_value' => 10000,
        'late_fee_policy_per' => LateFee::PER_DAY,
        'late_fee_policy_grace_days' => 2,
        'late_fee_waived' => false,
        'late_fee_rule_id' => 7,
    ]);

    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-03-03')))
        ->toBe(0.0)
        ->and(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-03-05')))
        ->toBe(20000.0);
});

test('percentage monthly late fees are calculated in whole monthly periods', function () {
    $invoice = new Invoice([
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 100000,
        'due_date' => '2026-01-01',
        'late_fee_policy_type' => LateFee::TYPE_PERCENTAGE,
        'late_fee_policy_value' => 5,
        'late_fee_policy_per' => LateFee::PER_MONTH,
        'late_fee_policy_grace_days' => 0,
        'late_fee_waived' => false,
        'late_fee_rule_id' => 8,
    ]);

    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-01-31')))
        ->toBe(5000.0);
});

test('waived late-fee policies calculate no new fee', function () {
    $invoice = new Invoice([
        'status' => Invoice::STATUS_OVERDUE,
        'total_amount' => 100000,
        'due_date' => '2026-01-01',
        'late_fee_policy_type' => InvoiceLateFeePolicy::MODE_NONE,
        'late_fee_waived' => true,
        'late_fee_rule_id' => null,
    ]);

    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-02-15')))
        ->toBe(0.0);
});

test('late-fee policy formats rates and document labels', function () {
    $rule = new LateFee([
        'name' => 'Daily charge',
        'type' => LateFee::TYPE_FIXED,
        'value' => 5000,
        'per' => LateFee::PER_DAY,
        'grace_days' => 1,
    ]);

    expect(InvoiceLateFeePolicy::formatRate(LateFee::TYPE_PERCENTAGE, 2.5, LateFee::PER_MONTH))
        ->toBe('2.5% / month')
        ->and(InvoiceLateFeePolicy::formatDocumentRuleLabel(
            InvoiceLateFeePolicy::MODE_RULE,
            'Daily charge',
            LateFee::TYPE_FIXED,
            5000,
            LateFee::PER_DAY,
            1,
        ))->toBe('Daily charge — MMK 5,000 per day · 1 grace day')
        ->and(InvoiceLateFeePolicy::formatOptionLabel($rule))
        ->toBe('Daily charge — MMK 5,000 / day · 1 grace day')
        ->and(InvoiceLateFeePolicy::formatDocumentMoney(5000.5))
        ->toBe('MMK 5,000.50');
});

test('late-fee policy reports overdue and chargeable days', function () {
    $invoice = new Invoice([
        'status' => Invoice::STATUS_OVERDUE,
        'due_date' => '2026-03-01',
        'late_fee_policy_type' => LateFee::TYPE_FIXED,
        'late_fee_policy_value' => 10000,
        'late_fee_policy_per' => LateFee::PER_DAY,
        'late_fee_policy_grace_days' => 2,
        'late_fee_waived' => false,
    ]);

    expect(InvoiceLateFeePolicy::daysPastDue($invoice, Carbon::parse('2026-03-05')))
        ->toBe(4)
        ->and(InvoiceLateFeePolicy::chargeableDays($invoice, Carbon::parse('2026-03-02')))
        ->toBe(0)
        ->and(InvoiceLateFeePolicy::chargeableDays($invoice, Carbon::parse('2026-03-05')))
        ->toBe(2)
        ->and(InvoiceLateFeePolicy::formatDocumentMoney(5000))
        ->toBe('MMK 5,000');
});

test('late-fee policy preserves an existing fee for paid invoices', function () {
    $invoice = new Invoice([
        'status' => Invoice::STATUS_PAID,
        'late_fee' => 12500,
        'due_date' => '2026-01-01',
        'late_fee_policy_type' => LateFee::TYPE_FIXED,
        'late_fee_policy_value' => 10000,
        'late_fee_policy_per' => LateFee::PER_DAY,
        'late_fee_policy_grace_days' => 0,
        'late_fee_waived' => false,
    ]);

    expect(InvoiceLateFeePolicy::calculateAmount($invoice, Carbon::parse('2026-02-01')))
        ->toBe(12500.0);
});

test('late-fee model exposes supported rule options', function () {
    expect(LateFee::types())
        ->toBe([LateFee::TYPE_FIXED, LateFee::TYPE_PERCENTAGE])
        ->and(LateFee::perOptions())
        ->toBe([LateFee::PER_DAY, LateFee::PER_MONTH])
        ->and(LateFee::statuses())
        ->toBe([LateFee::STATUS_ACTIVE, LateFee::STATUS_INACTIVE]);
});
