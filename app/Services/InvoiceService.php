<?php

namespace App\Services;

use App\Exceptions\ConcurrentConflictException;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Utility;
use App\Services\Concerns\AppliesBillingPropertyFilters;
use App\Services\Concerns\AppliesListQuery;
use App\Support\AdminListSorts;
use App\Support\BillingEagerLoads;
use App\Support\ContractInvoiceSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    use AppliesBillingPropertyFilters;
    use AppliesListQuery;

    public function __construct(
        private readonly InvoiceDocumentService $invoiceDocumentService,
        private readonly ContractLifecycleService $contractLifecycleService,
        private readonly InvoiceLateFeeService $invoiceLateFeeService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Invoice::query()->with(BillingEagerLoads::invoiceList());

        $this->applyInvoiceSearch($query, $params);
        $this->applyInvoiceBuildingRoomFilters($query, $params);
        $this->applyDateRangeFilter($query, $params, 'issued_date', 'issued_from', 'issued_to');
        $this->applyDateRangeFilter($query, $params, 'due_date', 'due_from', 'due_to');
        $this->applyInvoicePaymentStatusFilter($query, $params);
        $this->applyListQuery(
            $query,
            $params,
            [],
            AdminListSorts::invoices(),
            static function ($builder): void {
                // Prefer issue chronology so reseeded Sale rows and same-day
                // settlements do not monopolize page 1 over Rent/Utility history.
                $builder->orderByDesc('invoices.issued_date')
                    ->orderByDesc('invoices.due_date')
                    ->orderByDesc('invoices.id');
            },
        );

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyInvoiceSearch($query, array $params): void
    {
        if (empty($params['search'])) {
            return;
        }

        $search = $params['search'];

        $query->where(function ($builder) use ($search): void {
            $builder->where('invoice_number', 'like', '%'.$search.'%')
                ->orWhereHas('contract.user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
                ->orWhereHas('contract.secondUser', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
                ->orWhereHas('contract.room', function ($roomQuery) use ($search): void {
                    $roomQuery->where('room_number', 'like', '%'.$search.'%');
                })
                ->orWhereHas('utility.room', function ($roomQuery) use ($search): void {
                    $roomQuery->where('room_number', 'like', '%'.$search.'%');
                })
                ->orWhereHas('utilities.room', function ($roomQuery) use ($search): void {
                    $roomQuery->where('room_number', 'like', '%'.$search.'%');
                });
        });
    }

    /**
     * Building/room filters must include Utility-sourced invoices that resolve
     * property through utility.room when contract.room is absent.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyInvoiceBuildingRoomFilters($query, array $params): void
    {
        if (! empty($params['building_id'])) {
            $buildingId = $params['building_id'];
            $query->where(function ($builder) use ($buildingId): void {
                $builder->whereHas('contract.room', function ($roomQuery) use ($buildingId): void {
                    $roomQuery->where('building_id', $buildingId);
                })->orWhereHas('utility.room', function ($roomQuery) use ($buildingId): void {
                    $roomQuery->where('building_id', $buildingId);
                })->orWhereHas('utilities.room', function ($roomQuery) use ($buildingId): void {
                    $roomQuery->where('building_id', $buildingId);
                });
            });
        }

        if (! empty($params['room_id'])) {
            $roomId = $params['room_id'];
            $query->where(function ($builder) use ($roomId): void {
                $builder->whereHas('contract', function ($contractQuery) use ($roomId): void {
                    $contractQuery->where('room_id', $roomId);
                })->orWhereHas('utility', function ($utilityQuery) use ($roomId): void {
                    $utilityQuery->where('room_id', $roomId);
                })->orWhereHas('utilities', function ($utilityQuery) use ($roomId): void {
                    $utilityQuery->where('room_id', $roomId);
                });
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyInvoicePaymentStatusFilter($query, array $params): void
    {
        $paymentStatus = $params['payment_status'] ?? $params['status'] ?? null;

        if ($paymentStatus === 'draft') {
            $query->where('status', 'draft');

            return;
        }

        $query->where('status', '!=', 'draft');

        if (empty($paymentStatus) || $paymentStatus === 'all_approved') {
            return;
        }

        if ($paymentStatus === 'unpaid') {
            $query->where('status', 'issued');

            return;
        }

        $query->where('status', $paymentStatus);
    }

    public function find(int $id): Invoice
    {
        return Invoice::query()
            ->with(BillingEagerLoads::invoice())
            ->findOrFail($id);
    }

    /**
     * Confirm a pending draft invoice (review edits + Late Fee Rule + Issued transition).
     *
     * Alias for {@see issue()} used by the Invoice Approval Confirm flow.
     *
     * @param  array{
     *     late_fee_selection?: int|string|null,
     *     late_fee_rule_id?: int|null,
     *     late_fee_waived?: bool|null,
     *     due_date?: string,
     *     items?: list<array{id: int, current_reading?: float|int|null, unit_price?: float|int|null}>
     * }  $data
     */
    public function confirm(Invoice $invoice, array $data = []): Invoice
    {
        return $this->issue($invoice, $data);
    }

    /**
     * Finalize a draft invoice to Issued (authoritative totals, Late Fee snapshot, approver).
     *
     * Source ownership fields (contract, utility) are not editable here.
     * Safe line corrections (current unit / unit price) are recalculated server-side.
     *
     * @param  array{
     *     late_fee_selection?: int|string|null,
     *     late_fee_rule_id?: int|null,
     *     late_fee_waived?: bool|null,
     *     due_date?: string,
     *     items?: list<array{id: int, current_reading?: float|int|null, unit_price?: float|int|null}>
     * }  $data
     */
    public function issue(Invoice $invoice, array $data = []): Invoice
    {
        $this->assertNoSourceOwnershipEditsOnConfirm($data);

        $issued = DB::transaction(function () use ($invoice, $data): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'draft') {
                throw new ConcurrentConflictException('Only draft invoices can be issued.');
            }

            $reviewData = $data;
            unset($reviewData['items']);
            $this->applyDraftReviewUpdates($locked, $reviewData);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->applyConfirmInvoiceItemCorrections($locked, $data['items']);
                $this->recalculateInvoiceTotal($locked);
            }

            $policySnapshot = $this->invoiceLateFeeService->approvalSnapshot($locked, $data);

            $issuedDate = $locked->issued_date?->toDateString() ?? now()->toDateString();

            $locked->update([
                ...$policySnapshot,
                'status' => 'issued',
                // Prefer existing draft issue date; otherwise confirmation moment.
                'issued_date' => $issuedDate,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                // Late fee amount stays 0 until due + grace (scheduler accrues later).
                'late_fee' => 0,
            ]);

            return $locked->fresh(BillingEagerLoads::invoice());
        });

        // Email is best-effort — never roll back a successful issue on mail failure.
        // sendEmail resolves CURRENT party emails once (joint contracts included).
        try {
            $this->invoiceDocumentService->sendEmail($issued, []);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $issued;
    }

    /**
     * Persist Late Fee Rule selection on a draft invoice (shared List ↔ Detail state).
     *
     * @param  array{late_fee_selection?: int|string|null, late_fee_rule_id?: int|null, late_fee_waived?: bool|null}  $data
     */
    public function updateLateFeePolicy(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updated = $this->invoiceLateFeeService->assignDraftSelection($locked, $data);

            return $updated->fresh(BillingEagerLoads::invoice());
        });
    }

    public function generateFromContract(Contract $contract, ?Carbon $billingMonth = null): Invoice
    {
        if ($contract->status !== Contract::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Invoices can only be generated for active contracts.');
        }

        return DB::transaction(function () use ($contract, $billingMonth): Invoice {
            $contract->loadMissing(['room', 'paymentPlan']);
            $period = $this->normalizeBillingMonth($billingMonth ?? now());

            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $this->findOrCreateDraftInvoice($lockedContract, $period);
            $this->ensureContractChargeItem($invoice, $lockedContract, $period);
            $this->appendApprovedUtilitiesForPeriod($invoice, $lockedContract, $period);
            $this->recalculateInvoiceTotal($invoice);

            return $invoice->fresh(BillingEagerLoads::invoice());
        });
    }

    /**
     * Auto-create draft invoices whose generate date (due_date - 7 days) is on or before $asOf.
     * Skips Sale Full Payment and periods that already have an invoice.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function generateScheduledInvoices(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $created = 0;
        $skipped = 0;
        $errors = 0;

        $contracts = Contract::query()
            ->with(['room', 'paymentPlan'])
            ->where('status', Contract::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->where('type', 'rent')
                    ->orWhere(function ($saleQuery): void {
                        $saleQuery->where('type', 'sale')
                            ->where('payment_type', 'installment');
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($contracts as $contract) {
            if (! ContractInvoiceSchedule::supportsRecurringGeneration($contract)) {
                $skipped++;

                continue;
            }

            if ($asOf->lt(Carbon::parse($contract->start_date)->startOfDay())) {
                $skipped++;

                continue;
            }

            foreach (ContractInvoiceSchedule::periodsDueForGeneration($contract, $asOf) as $period) {
                $billingMonth = $period['billing_month'];

                $exists = Invoice::query()
                    ->where('contract_id', $contract->id)
                    ->whereDate('billing_month', $billingMonth->toDateString())
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                try {
                    $this->generateFromContract($contract, $billingMonth);
                    $created++;
                } catch (ConcurrentConflictException) {
                    $skipped++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $errors++;
                }
            }
        }

        $this->contractLifecycleService->syncEndedRentContracts($asOf);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    public function generateInvoiceNumber(): string
    {
        $lastSequence = Invoice::query()
            ->where('invoice_number', 'like', 'INV-%')
            ->pluck('invoice_number')
            ->map(function (string $number): int {
                if (! preg_match('/^INV-(\d+)$/', $number, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        return 'INV-'.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice
    {
        return Invoice::query()->create([
            ...$data,
            'invoice_number' => $data['invoice_number'] ?? $this->generateInvoiceNumber(),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ])->load(BillingEagerLoads::invoice());
    }

    public function generateFromUtility(Utility $utility): Invoice
    {
        return DB::transaction(function () use ($utility): Invoice {
            /** @var Utility $lockedUtility */
            $lockedUtility = Utility::query()
                ->whereKey($utility->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUtility->status !== 'approved') {
                throw new InvalidArgumentException('Only approved utility bills can be invoiced.');
            }

            // Idempotent: already linked to an invoice for this utility bill.
            if ($lockedUtility->invoice_id) {
                return $this->find((int) $lockedUtility->invoice_id);
            }

            $existingByUtilityColumn = Invoice::query()
                ->where('utility_id', $lockedUtility->id)
                ->lockForUpdate()
                ->first();

            if ($existingByUtilityColumn) {
                $lockedUtility->update(['invoice_id' => $existingByUtilityColumn->id]);

                return $this->find($existingByUtilityColumn->id);
            }

            $lockedUtility->load(['room', 'items.utilityType']);

            $contract = $this->resolveContractForUtility($lockedUtility);
            $period = $this->normalizeBillingMonth($lockedUtility->billing_month);

            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $this->findOrCreateDraftInvoice($lockedContract, $period);
            $this->ensureContractChargeItem($invoice, $lockedContract, $period);
            $this->appendUtilityItems($invoice, $lockedUtility);
            $this->recalculateInvoiceTotal($invoice);

            return $invoice->fresh(BillingEagerLoads::invoice());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw new InvalidArgumentException('Only draft invoices can be updated.');
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException('Only draft invoices can be updated.');
            }

            $this->applyDraftReviewUpdates($locked, $data);

            return $locked->fresh(BillingEagerLoads::invoice());
        });
    }

    /**
     * Apply allowed draft review edits (dates and optional update-path fields) and recalculate totals.
     *
     * Confirm/issue rejects source ownership keys before calling this; UpdateInvoice may still
     * include contract/items when editing drafts through the dedicated update endpoint.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyDraftReviewUpdates(Invoice $invoice, array $data): void
    {
        $attributes = [];

        if (array_key_exists('contract_id', $data) && $data['contract_id'] !== null) {
            $attributes['contract_id'] = (int) $data['contract_id'];
        }

        if (array_key_exists('utility_id', $data)) {
            $attributes['utility_id'] = $data['utility_id'];
        }

        if (array_key_exists('type', $data) && $data['type'] !== null) {
            $attributes['type'] = $data['type'];
        }

        if (array_key_exists('due_date', $data) && $data['due_date'] !== null) {
            $attributes['due_date'] = Carbon::parse($data['due_date'])->toDateString();
        }

        if (array_key_exists('issued_date', $data)) {
            $attributes['issued_date'] = $data['issued_date']
                ? Carbon::parse($data['issued_date'])->toDateString()
                : null;
        }

        if (array_key_exists('billing_month', $data)) {
            $attributes['billing_month'] = $data['billing_month']
                ? Carbon::parse($data['billing_month'])->startOfMonth()->toDateString()
                : null;
        }

        if (array_key_exists('late_fee', $data)) {
            $attributes['late_fee'] = $data['late_fee'];
        }

        if ($attributes !== []) {
            $invoice->update($attributes);
        }

        if (! empty($data['items']) && is_array($data['items'])) {
            $this->syncDraftInvoiceItems($invoice, $data['items']);
            $this->recalculateInvoiceTotal($invoice);
        }
    }

    /**
     * Confirm must not rewrite source ownership or submit calculated totals.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoSourceOwnershipEditsOnConfirm(array $data): void
    {
        $prohibited = [
            'contract_id' => 'Invoice customer/contract cannot be changed during confirm.',
            'utility_id' => 'Invoice utility source cannot be changed during confirm.',
            'type' => 'Invoice type cannot be changed during confirm.',
            'building_id' => 'Invoice building cannot be changed during confirm.',
            'room_id' => 'Invoice room cannot be changed during confirm.',
            'user_id' => 'Invoice customer ownership cannot be changed during confirm.',
            'issued_date' => 'Issue date is system-controlled and cannot be changed during confirm.',
            'billing_month' => 'Billing period cannot be changed during confirm.',
            'late_fee' => 'Late fee amount is calculated and cannot be set during confirm.',
            'total_amount' => 'Invoice total is calculated and cannot be set during confirm.',
        ];

        foreach ($prohibited as $key => $message) {
            if (array_key_exists($key, $data)) {
                throw new InvalidArgumentException($message);
            }
        }
    }

    /**
     * Apply domain-safe confirm corrections and recalculate usage/line amounts server-side.
     *
     * Metered (utility) lines: current_reading + unit_price.
     * Non-metered (rent/installment) lines: unit_price only (amount mirrors unit price).
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function applyConfirmInvoiceItemCorrections(Invoice $invoice, array $items): void
    {
        foreach ($items as $payload) {
            $itemId = (int) ($payload['id'] ?? 0);

            if ($itemId <= 0) {
                continue;
            }

            /** @var InvoiceItem|null $item */
            $item = InvoiceItem::query()
                ->whereKey($itemId)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new InvalidArgumentException('One or more invoice line items are invalid for this invoice.');
            }

            if ($item->isMetered()) {
                $previous = $item->previous_reading;
                $current = array_key_exists('current_reading', $payload)
                    ? $payload['current_reading']
                    : $item->current_reading;
                $unitPrice = array_key_exists('unit_price', $payload)
                    ? $payload['unit_price']
                    : $item->unit_price;

                if ($current !== null && $previous !== null && (float) $current < (float) $previous) {
                    throw new InvalidArgumentException('Current unit cannot be less than previous unit.');
                }

                $usage = ($previous !== null && $current !== null)
                    ? round((float) $current - (float) $previous, 2)
                    : $item->usage;

                $amount = ($usage !== null && $unitPrice !== null)
                    ? round((float) $usage * (float) $unitPrice, 2)
                    : (float) $item->amount;

                $item->update([
                    'current_reading' => $current,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => round((float) $amount, 2),
                ]);

                continue;
            }

            if (! array_key_exists('unit_price', $payload)) {
                continue;
            }

            $unitPrice = $payload['unit_price'];

            if ($unitPrice === null || (float) $unitPrice < 0) {
                throw new InvalidArgumentException('Unit price must be zero or greater.');
            }

            $amount = round((float) $unitPrice, 2);

            $item->update([
                'unit_price' => $amount,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncDraftInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $payload) {
            $itemId = (int) ($payload['id'] ?? 0);

            if ($itemId <= 0) {
                continue;
            }

            /** @var InvoiceItem|null $item */
            $item = InvoiceItem::query()
                ->whereKey($itemId)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new InvalidArgumentException('One or more invoice line items are invalid for this invoice.');
            }

            $previous = array_key_exists('previous_reading', $payload)
                ? $payload['previous_reading']
                : $item->previous_reading;
            $current = array_key_exists('current_reading', $payload)
                ? $payload['current_reading']
                : $item->current_reading;
            $unitPrice = array_key_exists('unit_price', $payload)
                ? $payload['unit_price']
                : $item->unit_price;

            $isMetered = $previous !== null || $current !== null
                || $item->previous_reading !== null
                || $item->current_reading !== null
                || $item->usage !== null;

            $usage = array_key_exists('usage', $payload) ? $payload['usage'] : $item->usage;
            $amount = array_key_exists('amount', $payload) ? $payload['amount'] : $item->amount;

            if ($isMetered && $previous !== null && $current !== null) {
                $usage = round((float) $current - (float) $previous, 2);
                if ($unitPrice !== null) {
                    $amount = round($usage * (float) $unitPrice, 2);
                }
            } elseif (! $isMetered && array_key_exists('unit_price', $payload) && ! array_key_exists('amount', $payload)) {
                $amount = round((float) $unitPrice, 2);
            }

            $updates = [
                'previous_reading' => $previous,
                'current_reading' => $current,
                'usage' => $usage,
                'unit_price' => $unitPrice,
                'amount' => round((float) $amount, 2),
            ];

            if (array_key_exists('description', $payload) && $payload['description'] !== null) {
                $updates['description'] = $payload['description'];
            }

            $item->update($updates);
        }
    }

    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
                throw new InvalidArgumentException('Paid or cancelled invoices cannot be changed.');
            }

            $hasApprovedPayments = $locked->payments()
                ->where('status', Payment::STATUS_APPROVED)
                ->exists();

            $hasProtectedReceipts = $locked->payments()
                ->whereHas('receipt', function ($query): void {
                    $query->where('status', Receipt::STATUS_ISSUED)
                        ->orWhere('approval_status', Receipt::APPROVAL_APPROVED);
                })
                ->exists();

            if ($hasApprovedPayments || $hasProtectedReceipts) {
                throw new ConcurrentConflictException(
                    'This invoice cannot be cancelled because approved payments or receipts exist.',
                );
            }

            $locked->update(['status' => Invoice::STATUS_CANCELLED]);
        });
    }

    private function normalizeBillingMonth(Carbon|string $billingMonth): Carbon
    {
        return Carbon::parse($billingMonth)->startOfMonth();
    }

    private function resolveContractForUtility(Utility $utility): Contract
    {
        if ($utility->contract_id) {
            $linked = Contract::query()->find($utility->contract_id);

            if ($linked) {
                return $linked;
            }
        }

        $contract = Contract::query()
            ->where('room_id', $utility->room_id)
            ->where('type', 'rent')
            ->whereIn('status', ['active', 'completed'])
            ->latest('id')
            ->first();

        if ($contract) {
            return $contract;
        }

        $contract = Contract::query()
            ->where('room_id', $utility->room_id)
            ->where('type', 'sale')
            ->whereIn('status', ['approved', 'active', 'completed'])
            ->latest('id')
            ->first();

        if ($contract) {
            return $contract;
        }

        throw new InvalidArgumentException('No active contract found for this room.');
    }

    private function findOrCreateDraftInvoice(Contract $contract, Carbon $billingMonth): Invoice
    {
        $billingMonthDate = $billingMonth->toDateString();

        $invoice = Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereDate('billing_month', $billingMonthDate)
            ->lockForUpdate()
            ->first();

        if ($invoice && $invoice->status !== 'draft') {
            throw new ConcurrentConflictException('An invoice for this billing period has already been finalized.');
        }

        if ($invoice) {
            return $invoice;
        }

        try {
            return Invoice::query()->create([
                'contract_id' => $contract->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'type' => $contract->type === 'sale' ? 'sale' : 'rent',
                'status' => 'draft',
                'billing_month' => $billingMonthDate,
                'due_date' => $this->resolveDueDate($contract, $billingMonth)->toDateString(),
                'total_amount' => 0,
                'created_by' => Auth::id(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueContractBillingPeriodViolation($exception)) {
                $existing = Invoice::query()
                    ->where('contract_id', $contract->id)
                    ->whereDate('billing_month', $billingMonthDate)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($existing->status !== 'draft') {
                    throw new ConcurrentConflictException('An invoice for this billing period has already been finalized.');
                }

                return $existing;
            }

            throw $exception;
        }
    }

    private function resolveDueDate(Contract $contract, Carbon $billingMonth): Carbon
    {
        $dueDay = ContractInvoiceSchedule::recurringDueDay($contract);

        return ContractInvoiceSchedule::dueDateInMonth($billingMonth, $dueDay);
    }

    private function ensureContractChargeItem(Invoice $invoice, Contract $contract, Carbon $billingMonth): void
    {
        $slug = $contract->type === 'sale' ? 'sale-installment' : 'monthly-rent';
        $chargeType = ChargeType::query()->where('slug', $slug)->first();

        if (! $chargeType) {
            return;
        }

        $exists = InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->where('charge_type_id', $chargeType->id)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = $this->resolveContractChargeAmount($contract);
        $description = $contract->type === 'sale'
            ? 'Sale installment — '.$billingMonth->format('F Y')
            : 'Monthly rent — '.$billingMonth->format('F Y');

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'charge_type_id' => $chargeType->id,
            'description' => $description,
            'unit_price' => $amount,
            'amount' => $amount,
        ]);
    }

    private function resolveContractChargeAmount(Contract $contract): float
    {
        if ($contract->type === 'sale') {
            $months = max((int) ($contract->duration_months ?? 1), 1);

            return round((float) $contract->contract_total / $months, 2);
        }

        return round((float) ($contract->room?->rent_price ?? $contract->contract_total), 2);
    }

    private function appendApprovedUtilitiesForPeriod(Invoice $invoice, Contract $contract, Carbon $billingMonth): void
    {
        $utilities = Utility::query()
            ->where('room_id', $contract->room_id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->where('status', 'approved')
            ->whereNull('invoice_id')
            ->with('items.utilityType')
            ->lockForUpdate()
            ->get();

        foreach ($utilities as $utility) {
            $this->appendUtilityItems($invoice, $utility);
        }
    }

    private function appendUtilityItems(Invoice $invoice, Utility $utility): void
    {
        if ($this->isUtilityInvoiced($utility)) {
            throw new ConcurrentConflictException('This utility bill has already been invoiced.');
        }

        $utility->loadMissing('items.utilityType');
        $chargeType = ChargeType::query()->where('slug', 'utility-charges')->first();

        foreach ($utility->items as $item) {
            $typeName = $item->utilityType?->name ?? 'Utility';

            $duplicate = InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->where('description', $typeName)
                ->where('amount', $item->amount)
                ->exists();

            if ($duplicate) {
                continue;
            }

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'charge_type_id' => $chargeType?->id,
                'description' => $typeName,
                'previous_reading' => $item->previous_reading,
                'current_reading' => $item->current_reading,
                'usage' => $item->usage,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ]);
        }

        $utility->update(['invoice_id' => $invoice->id]);

        if (! $invoice->utility_id) {
            $invoice->update(['utility_id' => $utility->id]);
        }
    }

    private function recalculateInvoiceTotal(Invoice $invoice): void
    {
        $subtotal = (float) InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount');

        $invoice->update([
            'total_amount' => round($subtotal, 2),
        ]);
    }

    private function isUtilityInvoiced(Utility $utility): bool
    {
        if ($utility->invoice_id) {
            return true;
        }

        return Invoice::query()->where('utility_id', $utility->id)->exists();
    }

    private function isUniqueContractBillingPeriodViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'invoices_contract_id_billing_month_unique')
            || (str_contains($message, 'UNIQUE constraint failed') && str_contains($message, 'billing_month'))
            || (str_contains($message, 'Duplicate entry') && str_contains($message, 'billing_month'));
    }
}
