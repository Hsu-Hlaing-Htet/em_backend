<?php

namespace Database\Seeders\Support;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class BillingSeederSupport
{
    private static int $invoiceSequence = 0;

    private static int $paymentSequence = 0;

    private static int $receiptSequence = 0;

    public static function resetSequences(): void
    {
        self::$invoiceSequence = 0;
        self::$paymentSequence = 0;
        self::$receiptSequence = 0;
    }

    public static function nextInvoiceNumber(): string
    {
        self::$invoiceSequence++;

        return 'INV-'.str_pad((string) self::$invoiceSequence, 6, '0', STR_PAD_LEFT);
    }

    public static function nextPaymentNumber(): string
    {
        self::$paymentSequence++;

        return 'PAY-'.str_pad((string) self::$paymentSequence, 6, '0', STR_PAD_LEFT);
    }

    public static function nextReceiptNumber(): string
    {
        self::$receiptSequence++;

        return 'RCP-'.str_pad((string) self::$receiptSequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{charge_type_id: int|null, description: string, amount: float}>  $items
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
        $totalAmount = round(collect($items)->sum('amount'), 2);
        $isIssued = in_array($status, ['issued', 'partial', 'paid', 'overdue'], true);

        $invoice = Invoice::query()->create([
            'contract_id' => $contractId,
            'utility_id' => $utilityId,
            'created_by' => $admin->id,
            'approved_by' => $isIssued ? $admin->id : null,
            'approved_at' => $isIssued ? $issuedDate->copy()->subDay() : null,
            'invoice_number' => self::nextInvoiceNumber(),
            'type' => $type,
            'issued_date' => $isIssued ? $issuedDate->toDateString() : null,
            'due_date' => $dueDate->toDateString(),
            'late_fee' => $lateFee,
            'total_amount' => $totalAmount,
            'status' => $status,
        ]);

        foreach ($items as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                ...$item,
            ]);
        }

        return $invoice;
    }

    public static function createApprovedPayment(
        User $admin,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        float $amount,
        Carbon $paymentDate,
        ?string $note = null,
    ): Payment {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => $paymentDate->copy()->addDay(),
            'payment_number' => self::nextPaymentNumber(),
            'amount' => round($amount, 2),
            'proof_image_path' => 'payments/proof-'.$invoice->invoice_number.'.jpg',
            'note' => $note ?? 'Payment verified and approved.',
            'payment_date' => $paymentDate->toDateString(),
            'status' => 'approved',
        ]);
    }

    public static function createPendingPayment(
        User $admin,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        float $amount,
        Carbon $paymentDate,
    ): Payment {
        return Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'created_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
            'payment_number' => self::nextPaymentNumber(),
            'amount' => round($amount, 2),
            'proof_image_path' => null,
            'note' => 'Payment submitted and awaiting verification.',
            'payment_date' => $paymentDate->toDateString(),
            'status' => 'pending',
        ]);
    }

    public static function createIssuedReceipt(User $admin, Payment $payment, Carbon $issuedAt): Receipt
    {
        $receiptNumber = self::nextReceiptNumber();

        return Receipt::query()->create([
            'payment_id' => $payment->id,
            'receipt_number' => $receiptNumber,
            'receipt_pdf_path' => 'receipts/'.$receiptNumber.'.pdf',
            'status' => 'issued',
            'issued_at' => $issuedAt,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);
    }

    public static function settleInvoice(
        User $admin,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        Carbon $paymentDate,
    ): Payment {
        $payment = self::createApprovedPayment(
            $admin,
            $invoice,
            $paymentMethod,
            (float) $invoice->total_amount,
            $paymentDate,
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
