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
    private static int $invoiceSequence = 0;

    private static int $receiptSequence = 0;

    public static function resetSequences(): void
    {
        $lastInvoice = Invoice::query()
            ->where('invoice_number', 'like', 'INV-%')
            ->pluck('invoice_number')
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        $lastReceipt = Receipt::query()
            ->where('receipt_number', 'like', 'RCP-%')
            ->pluck('receipt_number')
            ->map(fn (string $number): int => (int) substr($number, 4))
            ->max() ?? 0;

        self::$invoiceSequence = $lastInvoice;
        self::$receiptSequence = $lastReceipt;
    }

    public static function nextInvoiceNumber(): string
    {
        self::$invoiceSequence++;

        return 'INV-'.str_pad((string) self::$invoiceSequence, 6, '0', STR_PAD_LEFT);
    }

    public static function nextReceiptNumber(): string
    {
        self::$receiptSequence++;

        return 'RCP-'.str_pad((string) self::$receiptSequence, 6, '0', STR_PAD_LEFT);
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
