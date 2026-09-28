<?php

use App\Models\Invoice;
use App\Models\LateFee;
use App\Support\InvoiceLateFeePolicy;
use Database\Seeders\LateFeeSeeder;
use Database\Seeders\Support\LateFeeSeedSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('late fee seeder creates three active demo rules without duplicates', function (): void {
    (new LateFeeSeeder)->run();
    (new LateFeeSeeder)->run();

    $names = LateFee::query()->orderBy('name')->pluck('name')->all();

    expect($names)->toBe([
        LateFeeSeedSupport::RULE_EXTENDED,
        LateFeeSeedSupport::RULE_PERCENTAGE,
        LateFeeSeedSupport::RULE_STANDARD,
    ]);

    expect(LateFee::query()->where('name', LateFeeSeedSupport::RULE_STANDARD)->first())
        ->type->toBe('fixed')
        ->and((float) LateFee::query()->where('name', LateFeeSeedSupport::RULE_STANDARD)->value('value'))->toBe(5000.0)
        ->and(LateFee::query()->where('name', LateFeeSeedSupport::RULE_STANDARD)->value('per'))->toBe('day')
        ->and((int) LateFee::query()->where('name', LateFeeSeedSupport::RULE_STANDARD)->value('grace_days'))->toBe(3)
        ->and(LateFee::query()->where('name', LateFeeSeedSupport::RULE_STANDARD)->value('status'))->toBe('active');

    expect(LateFee::query()->where('name', LateFeeSeedSupport::RULE_EXTENDED)->first())
        ->type->toBe('fixed')
        ->and((float) LateFee::query()->where('name', LateFeeSeedSupport::RULE_EXTENDED)->value('value'))->toBe(10000.0)
        ->and((int) LateFee::query()->where('name', LateFeeSeedSupport::RULE_EXTENDED)->value('grace_days'))->toBe(5);

    expect(LateFee::query()->where('name', LateFeeSeedSupport::RULE_PERCENTAGE)->first())
        ->type->toBe('percentage')
        ->and((float) LateFee::query()->where('name', LateFeeSeedSupport::RULE_PERCENTAGE)->value('value'))->toBe(1.5)
        ->and(LateFee::query()->where('name', LateFeeSeedSupport::RULE_PERCENTAGE)->value('per'))->toBe('month')
        ->and((int) LateFee::query()->where('name', LateFeeSeedSupport::RULE_PERCENTAGE)->value('grace_days'))->toBe(5);

    expect(LateFee::query()->where('name', LateFeeSeedSupport::LEGACY_STANDARD)->exists())->toBeFalse();
});

test('seed late fee attributes leave drafts unselected and snapshot issued invoices', function (): void {
    (new LateFeeSeeder)->run();

    $draft = LateFeeSeedSupport::attributesForSeedInvoice(
        Invoice::STATUS_DRAFT,
        'rent',
        500000,
        'INV-000100',
        Carbon::parse('2026-09-22'),
        Carbon::parse('2026-09-28'),
    );

    expect($draft['late_fee_rule_id'])->toBeNull()
        ->and($draft['late_fee_policy_locked'])->toBeFalse()
        ->and((float) $draft['late_fee'])->toBe(0.0);

    $rent = LateFeeSeedSupport::attributesForSeedInvoice(
        Invoice::STATUS_OVERDUE,
        'rent',
        500000,
        'INV-000101',
        Carbon::parse('2026-09-22'),
        Carbon::parse('2026-09-28'),
    );

    expect($rent['late_fee_policy_locked'])->toBeTrue()
        ->and($rent['late_fee_policy_name'])->toBe(LateFeeSeedSupport::RULE_STANDARD)
        ->and((float) $rent['late_fee_policy_value'])->toBe(5000.0)
        ->and((int) $rent['late_fee_policy_grace_days'])->toBe(3)
        // 6 overdue days − 3 grace = 3 chargeable × 5000
        ->and((float) $rent['late_fee'])->toBe(15000.0);

    $sale = LateFeeSeedSupport::attributesForSeedInvoice(
        Invoice::STATUS_OVERDUE,
        'sale',
        20_000_000,
        'INV-000102',
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-28'),
    );

    expect($sale['late_fee_policy_name'])->toBe(LateFeeSeedSupport::RULE_PERCENTAGE)
        ->and((float) $sale['late_fee_policy_value'])->toBe(1.5)
        ->and($sale['late_fee_policy_per'])->toBe('month')
        ->and((float) $sale['late_fee'])->toBeGreaterThan(0.0);

    $probe = new Invoice([
        'due_date' => Carbon::parse('2026-09-01'),
        'total_amount' => 20_000_000,
        'status' => Invoice::STATUS_OVERDUE,
        ...InvoiceLateFeePolicy::snapshotFromRule(
            LateFee::query()->where('name', LateFeeSeedSupport::RULE_PERCENTAGE)->firstOrFail(),
            locked: true,
        ),
    ]);

    expect((float) $sale['late_fee'])->toBe(
        InvoiceLateFeePolicy::calculateAmount($probe, Carbon::parse('2026-09-28'))
    );
});

test('non-overdue issued seed invoice keeps late fee at zero with locked rule', function (): void {
    (new LateFeeSeeder)->run();

    $attrs = LateFeeSeedSupport::attributesForSeedInvoice(
        Invoice::STATUS_ISSUED,
        'rent',
        500000,
        'INV-000200',
        Carbon::parse('2026-09-25'),
        Carbon::parse('2026-09-28'),
    );

    expect($attrs['late_fee_policy_locked'])->toBeTrue()
        ->and($attrs['late_fee_policy_name'])->toBe(LateFeeSeedSupport::RULE_STANDARD)
        ->and((float) $attrs['late_fee'])->toBe(0.0);
});
