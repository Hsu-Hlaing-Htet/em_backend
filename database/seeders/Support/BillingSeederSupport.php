<?php

namespace Database\Seeders\Support;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class BillingSeederSupport
{
    public static function resetSequences(): void
    {
        SeedNumberGenerator::reset();
    }

    public static function nextInvoiceNumber(): string
    {
        return SeedNumberGenerator::nextInvoiceNumber();
    }

    public static function isCanonicalInvoiceNumber(string $invoiceNumber): bool
    {
        return (bool) preg_match('/^INV-\d{6}$/', $invoiceNumber);
    }

    /**
     * Rename legacy INV-CF-* seed invoice numbers to INV-000001 style in place.
     * Preserves invoice IDs and payment/receipt foreign keys.
     */
    public static function normalizeLegacySeedInvoiceNumbers(): int
    {
        self::resetSequences();

        $renamed = 0;

        Invoice::query()
            ->where('invoice_number', 'like', 'INV-CF-%')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$renamed): void {
                $invoice->update([
                    'invoice_number' => self::nextInvoiceNumber(),
                ]);
                $renamed++;
            });

        return $renamed;
    }

    /**
     * Upsert a contract charge invoice using a stable business key
     * (contract + seed suffix / billing month), with a canonical INV-000001 number.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function upsertSeedChargeInvoice(
        string $seedKey,
        User $admin,
        Contract $contract,
        string $type,
        string $status,
        Carbon $issuedDate,
        Carbon $dueDate,
        array $items,
        float $lateFee = 0,
        ?Carbon $billingMonth = null,
    ): Invoice {
        $existing = self::findSeedChargeInvoice($contract, $seedKey, $billingMonth);

        $invoiceNumber = ($existing && self::isCanonicalInvoiceNumber((string) $existing->invoice_number))
            ? (string) $existing->invoice_number
            : self::nextInvoiceNumber();

        // When renaming a legacy INV-CF row, update the number in place first so
        // updateOrCreate keeps the same primary key and payment FKs stay valid.
        if ($existing && (string) $existing->invoice_number !== $invoiceNumber) {
            $existing->update(['invoice_number' => $invoiceNumber]);
        }

        return self::upsertInvoice(
            $invoiceNumber,
            $admin,
            $contract->id,
            null,
            $type,
            $status,
            $issuedDate,
            $dueDate,
            $items,
            $lateFee,
            $billingMonth,
        );
    }

    public static function findSeedChargeInvoice(
        Contract $contract,
        string $seedKey,
        ?Carbon $billingMonth = null,
    ): ?Invoice {
        $legacyNumber = sprintf('INV-CF-%s-%s', $contract->contract_number, $seedKey);

        $legacy = Invoice::query()
            ->where('invoice_number', $legacyNumber)
            ->first();

        if ($legacy) {
            return $legacy;
        }

        if ($billingMonth) {
            return Invoice::query()
                ->where('contract_id', $contract->id)
                ->whereDate('billing_month', $billingMonth->copy()->startOfMonth()->toDateString())
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->orderBy('id')
                ->first();
        }

        $description = match (true) {
            $seedKey === 'DEP' => 'Sale booking deposit',
            $seedKey === 'FULL' => 'Sale purchase settlement',
            $seedKey === 'SETTLE' => 'Sale installment early settlement',
            default => null,
        };

        if ($description === null) {
            return null;
        }

        return Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereNull('billing_month')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereHas('items', fn ($query) => $query->where('description', $description))
            ->orderBy('id')
            ->first();
    }

    public static function nextReceiptNumber(): string
    {
        return SeedNumberGenerator::nextReceiptNumber();
    }

    public static function nextSaleContractNumber(): string
    {
        return SeedNumberGenerator::nextSaleContractNumber();
    }

    public static function nextRentContractNumber(): string
    {
        return SeedNumberGenerator::nextRentContractNumber();
    }

    public static function storePaymentProof(string $relativePath): string
    {
        SeedAssetImage::assertRoomAssetLibraryPresent();

        return SeedAssetImage::storePaymentProof($relativePath);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>|null  $utilityIds
     */
    public static function upsertInvoice(
        string $invoiceNumber,
        User $admin,
        int $contractId,
        ?int $utilityId,
        string $type,
        string $status,
        Carbon $issuedDate,
        Carbon $dueDate,
        array $items,
        float $lateFee = 0,
        ?Carbon $billingMonth = null,
        ?array $utilityIds = null,
    ): Invoice {
        $totalAmount = round(collect($items)->sum('amount'), 2);
        $isIssued = in_array($status, ['issued', 'partial', 'paid', 'overdue'], true);
        $billingMonthDate = $billingMonth?->copy()->startOfMonth()->toDateString();

        $invoice = Invoice::query()->updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'contract_id' => $contractId,
                'utility_id' => $utilityIds !== null ? null : $utilityId,
                'billing_month' => $billingMonthDate,
                'created_by' => $admin->id,
                'approved_by' => $isIssued ? $admin->id : null,
                'approved_at' => $isIssued ? $issuedDate->copy()->subDay() : null,
                'type' => $type,
                'issued_date' => $isIssued ? $issuedDate->toDateString() : null,
                'due_date' => $dueDate->toDateString(),
                'late_fee' => $lateFee,
                'total_amount' => $totalAmount,
                'status' => $status,
            ],
        );

        $invoice->items()->delete();

        foreach ($items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                ...$item,
            ]);
        }

        if ($utilityIds !== null) {
            Utility::query()
                ->whereIn('id', $utilityIds)
                ->update(['invoice_id' => $invoice->id]);
        }

        return $invoice->fresh('items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>|null  $utilityIds
     */
    public static function upsertConsolidatedInvoice(
        string $invoiceNumber,
        User $admin,
        Contract $contract,
        Carbon $billingMonth,
        string $type,
        string $status,
        Carbon $issuedDate,
        Carbon $dueDate,
        array $items,
        float $lateFee = 0,
        ?array $utilityIds = null,
    ): Invoice {
        return self::upsertInvoice(
            $invoiceNumber,
            $admin,
            $contract->id,
            null,
            $type,
            $status,
            $issuedDate,
            $dueDate,
            $items,
            $lateFee,
            $billingMonth,
            $utilityIds,
        );
    }

    /**
     * @deprecated Use upsertInvoice for idempotent seeding.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function createInvoice(
        User $admin,
        int $contractId,
        ?int $utilityId,
        string $type,
        string $status,
        Carbon $issuedDate,
        Carbon $dueDate,
        array $items,
        float $lateFee = 0,
    ): Invoice {
        return self::upsertInvoice(
            self::nextInvoiceNumber(),
            $admin,
            $contractId,
            $utilityId,
            $type,
            $status,
            $issuedDate,
            $dueDate,
            $items,
            $lateFee,
        );
    }

    public static function upsertPayment(
        Invoice $invoice,
        string $noteKey,
        array $attributes,
    ): Payment {
        $existing = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('note', 'like', $noteKey.'%')
            ->first();

        $noteBody = (string) ($attributes['note'] ?? '');
        $attributes['note'] = str_starts_with($noteBody, $noteKey)
            ? $noteBody
            : $noteKey.($noteBody !== '' ? ' '.$noteBody : '');

        if (! empty($attributes['proof_image_path'])) {
            $attributes['proof_image_path'] = self::storePaymentProof((string) $attributes['proof_image_path']);
        }

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            ...$attributes,
        ]);
    }

    public static function invoiceAmountDue(Invoice $invoice): float
    {
        return round((float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0), 2);
    }

    public static function upsertReceipt(
        Payment $payment,
        User $admin,
        string $status,
        string $approvalStatus,
        ?Carbon $issuedAt = null,
        ?string $rejectionNote = null,
    ): Receipt {
        $receipt = Receipt::query()->firstOrNew(['payment_id' => $payment->id]);

        if (! $receipt->exists) {
            $receipt->receipt_number = self::nextReceiptNumber();
            $receipt->created_by = $admin->id;
        }

        $receipt->fill([
            'receipt_pdf_path' => $status === Receipt::STATUS_ISSUED
                ? 'receipts/'.$receipt->receipt_number.'.pdf'
                : null,
            'status' => $status,
            'approval_status' => $approvalStatus,
            'issued_at' => $issuedAt,
            'sent_at' => $status === Receipt::STATUS_ISSUED ? ($issuedAt ?? now()) : null,
            'sent_by' => $status === Receipt::STATUS_ISSUED ? $admin->id : null,
            'approved_by' => in_array($approvalStatus, ['approved', 'rejected'], true) ? $admin->id : null,
            'approved_at' => in_array($approvalStatus, ['approved', 'rejected'], true) ? ($issuedAt ?? now()) : null,
        ]);
        $receipt->save();

        return $receipt;
    }

    public static function createApprovedPayment(
        User $admin,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        float $amount,
        Carbon $paymentDate,
        ?string $note = null,
        ?User $createdBy = null,
    ): Payment {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'created_by' => ($createdBy ?? $admin)->id,
            'approved_by' => $admin->id,
            'approved_at' => $paymentDate->copy()->addDay(),
            'amount' => round($amount, 2),
            'proof_image_path' => 'payments/proof-'.$invoice->invoice_number.'.jpg',
            'note' => $note ?? 'Payment verified and approved.',
            'rejection_reason' => null,
            'payment_date' => $paymentDate->toDateString(),
            'status' => 'approved',
        ]);
    }

    public static function createPendingPayment(
        User $createdBy,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        ?float $amount,
        Carbon $paymentDate,
        ?string $note = null,
    ): Payment {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'created_by' => $createdBy->id,
            'approved_by' => null,
            'approved_at' => null,
            'amount' => $amount !== null ? round($amount, 2) : null,
            'proof_image_path' => 'payments/pending-'.$invoice->invoice_number.'.jpg',
            'note' => $note ?? 'Payment submitted and awaiting verification.',
            'rejection_reason' => null,
            'payment_date' => $paymentDate->toDateString(),
            'status' => 'pending',
        ]);
    }

    public static function createRejectedPayment(
        User $admin,
        User $customer,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        Carbon $paymentDate,
        string $rejectionReason,
    ): Payment {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'created_by' => $customer->id,
            'approved_by' => $admin->id,
            'approved_at' => $paymentDate->copy()->addDay(),
            'amount' => null,
            'proof_image_path' => 'payments/rejected-'.$invoice->invoice_number.'.jpg',
            'note' => 'Customer submitted proof for verification.',
            'rejection_reason' => $rejectionReason,
            'payment_date' => $paymentDate->toDateString(),
            'status' => 'rejected',
        ]);
    }

    public static function createIssuedReceipt(User $admin, Payment $payment, Carbon $issuedAt): Receipt
    {
        return self::upsertReceipt(
            $payment,
            $admin,
            Receipt::STATUS_ISSUED,
            Receipt::APPROVAL_APPROVED,
            $issuedAt,
        );
    }

    public static function settleInvoice(
        User $admin,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        Carbon $paymentDate,
        ?User $createdBy = null,
    ): Payment {
        $payment = self::createApprovedPayment(
            $admin,
            $invoice,
            $paymentMethod,
            (float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0),
            $paymentDate,
            createdBy: $createdBy,
        );

        $invoice->update(['status' => 'paid']);
        self::createIssuedReceipt($admin, $payment, $paymentDate->copy()->addDay());

        return $payment;
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public static function paymentMethods(): Collection
    {
        return PaymentMethod::query()->where('status', 'active')->orderBy('id')->get();
    }
}
