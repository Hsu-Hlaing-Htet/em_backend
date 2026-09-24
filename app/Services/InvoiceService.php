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

    public function issue(Invoice $invoice): Invoice
    {
        $issued = DB::transaction(function () use ($invoice): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'draft') {
                throw new ConcurrentConflictException('Only draft invoices can be issued.');
            }

            $locked->update([
                'status' => 'issued',
                'issued_date' => now()->toDateString(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $locked->fresh(BillingEagerLoads::invoice());
        });

        // Email is best-effort — never roll back a successful issue on mail failure.
        try {
            $issued->loadMissing(['contract.user', 'contract.secondUser']);
            foreach ($issued->contract?->partyEmails() ?? [] as $email) {
                $this->invoiceDocumentService->sendEmail($issued, [
                    'email' => $email,
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $issued;
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

        $invoice->update($data);

        return $invoice->fresh(BillingEagerLoads::invoice());
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
