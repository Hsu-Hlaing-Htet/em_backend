<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Support\InvoiceLateFeePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceLateFeeService
{
    /**
     * @param  array{late_fee_selection?: int|string|null, late_fee_rule_id?: int|null, late_fee_waived?: bool|null}  $data
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
    public function resolveSelectionPayload(array $data, bool $locked): array
    {
        [$waived, $ruleId] = $this->normalizeSelection($data);

        if ($waived) {
            throw new InvalidArgumentException('Please select a Late Fee Rule.');
        }

        if ($ruleId === null) {
            throw new InvalidArgumentException('Please select a Late Fee Rule.');
        }

        $rule = LateFee::query()->find($ruleId);

        if (! $rule) {
            throw new InvalidArgumentException('The selected Late Fee Rule was not found.');
        }

        if ($rule->status !== LateFee::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only Active Late Fee Rules can be selected for new invoice approvals.');
        }

        return InvoiceLateFeePolicy::snapshotFromRule($rule, $locked);
    }

    /**
     * Persist the Admin selection on a draft invoice (shared List/Detail state).
     *
     * @param  array{late_fee_selection?: int|string|null, late_fee_rule_id?: int|null, late_fee_waived?: bool|null}  $data
     */
    public function assignDraftSelection(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new InvalidArgumentException('Late Fee Rule can only be changed on draft invoices.');
        }

        if ($invoice->late_fee_policy_locked) {
            throw new InvalidArgumentException('This invoice Late Fee policy is locked.');
        }

        $payload = $this->resolveSelectionPayload($data, locked: false);

        $invoice->update($payload);

        return $invoice->fresh();
    }

    /**
     * Snapshot + lock the selected policy during approval.
     *
     * @param  array{late_fee_selection?: int|string|null, late_fee_rule_id?: int|null, late_fee_waived?: bool|null}  $data
     * @return array<string, mixed>
     */
    public function approvalSnapshot(Invoice $invoice, array $data = []): array
    {
        if ($data !== [] && (
            array_key_exists('late_fee_selection', $data)
            || array_key_exists('late_fee_rule_id', $data)
            || array_key_exists('late_fee_waived', $data)
        )) {
            return $this->resolveSelectionPayload($data, locked: true);
        }

        // Fall back to the already-persisted draft selection (List/Detail shared field).
        if ($invoice->late_fee_rule_id) {
            return $this->resolveSelectionPayload([
                'late_fee_rule_id' => (int) $invoice->late_fee_rule_id,
                'late_fee_waived' => false,
            ], locked: true);
        }

        throw new InvalidArgumentException('Please select a Late Fee Rule.');
    }

    /**
     * @return array{updated: int, overdue: int}
     */
    public function applyDueLateFees(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $updated = 0;
        $overdue = 0;

        $invoices = Invoice::query()
            ->where('late_fee_policy_locked', true)
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            DB::transaction(function () use ($invoice, $asOf, &$updated, &$overdue): void {
                /** @var Invoice $locked */
                $locked = Invoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($locked->status, [
                    Invoice::STATUS_PAID,
                    Invoice::STATUS_CANCELLED,
                    Invoice::STATUS_DRAFT,
                ], true)) {
                    return;
                }

                $changes = [];

                if ($locked->due_date
                    && $asOf->greaterThan($locked->due_date->copy()->startOfDay())
                    && $locked->status === Invoice::STATUS_ISSUED
                ) {
                    $changes['status'] = Invoice::STATUS_OVERDUE;
                    $overdue++;
                }

                $amount = InvoiceLateFeePolicy::calculateAmount($locked, $asOf);

                if (round((float) $locked->late_fee, 2) !== $amount) {
                    $changes['late_fee'] = $amount;
                    $updated++;
                }

                if ($changes !== []) {
                    $locked->update($changes);
                }
            });
        }

        return [
            'updated' => $updated,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  array{late_fee_selection?: int|string|null, late_fee_rule_id?: int|null, late_fee_waived?: bool|null}  $data
     * @return array{0: bool, 1: int|null}
     */
    private function normalizeSelection(array $data): array
    {
        if (array_key_exists('late_fee_selection', $data)) {
            $selection = $data['late_fee_selection'];

            if ($selection === null || $selection === '') {
                return [false, null];
            }

            if ($selection === InvoiceLateFeePolicy::SELECTION_NONE || $selection === '0') {
                return [true, null];
            }

            return [false, (int) $selection];
        }

        $waived = (bool) ($data['late_fee_waived'] ?? false);
        $ruleId = array_key_exists('late_fee_rule_id', $data) && $data['late_fee_rule_id'] !== null
            ? (int) $data['late_fee_rule_id']
            : null;

        if ($waived) {
            return [true, null];
        }

        return [false, $ruleId];
    }
}
