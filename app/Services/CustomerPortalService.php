<?php

namespace App\Services;

use App\Http\Resources\Admin\PaymentResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class CustomerPortalService
{
    public function __construct(
        private readonly ResidentService $residentService,
        private readonly PaymentService $paymentService,
        private readonly InvoiceDocumentService $invoiceDocumentService,
        private readonly ReceiptDocumentService $receiptDocumentService,
        private readonly SaleContractDocumentService $saleContractDocumentService,
        private readonly RentContractDocumentService $rentContractDocumentService,
    ) {}

    public function dashboardSummary(User $user): array
    {
        $activeContracts = Contract::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'active'])
            ->count();

        $completedContracts = Contract::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $invoiceQuery = $this->customerInvoiceQuery($user->id);

        $unpaidInvoices = (clone $invoiceQuery)
            ->whereIn('status', ['issued', 'partial', 'overdue', 'unpaid'])
            ->count();

        $paidInvoices = (clone $invoiceQuery)
            ->where('status', 'paid')
            ->count();

        $paymentQuery = Payment::query()
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id));

        $totalPayments = (clone $paymentQuery)->count();

        $pendingPayments = (clone $paymentQuery)
            ->where('status', 'pending')
            ->count();

        $completedPayments = (clone $paymentQuery)
            ->whereIn('status', ['approved', 'completed'])
            ->count();

        $totalPaidAmount = (float) (clone $paymentQuery)
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');

        $recentPayments = (clone $paymentQuery)
            ->with(['invoice', 'paymentMethod'])
            ->latest('payment_date')
            ->limit(5)
            ->get();

        return [
            'active_contracts' => $activeContracts,
            'completed_contracts' => $completedContracts,
            'unpaid_invoices' => $unpaidInvoices,
            'paid_invoices' => $paidInvoices,
            'total_payments' => $totalPayments,
            'pending_payments' => $pendingPayments,
            'completed_payments' => $completedPayments,
            'total_paid_amount' => $totalPaidAmount,
            'recent_payments' => PaymentResource::collection($recentPayments)->resolve(),
        ];
    }

    public function profile(User $user): User
    {
        return $this->residentService->find($user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return $this->residentService->update($user, $data);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateContracts(User $user, array $params): LengthAwarePaginator
    {
        $query = Contract::query()
            ->with(['user.profile', 'room.building', 'paymentPlan'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'active', 'completed']);

        if (! empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->latest('id')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findContract(User $user, int $contractId): Contract
    {
        return Contract::query()
            ->with(['user.profile', 'room.building', 'paymentPlan'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'active', 'completed'])
            ->findOrFail($contractId);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateInvoices(User $user, array $params): LengthAwarePaginator
    {
        $query = $this->customerInvoiceQuery($user->id)
            ->with(['contract.user.profile', 'contract.room.building', 'items.chargeType', 'payments', 'approver']);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        return $query->latest('id')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findInvoice(User $user, int $invoiceId): Invoice
    {
        return $this->customerInvoiceQuery($user->id)
            ->with(['contract.user.profile', 'contract.room.building', 'utility', 'items.chargeType', 'payments.paymentMethod', 'payments.receipt', 'approver'])
            ->findOrFail($invoiceId);
    }

    public function invoicePaidAmount(Invoice $invoice): float
    {
        return (float) $invoice->payments
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginatePayments(User $user, array $params): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with(['invoice.contract.room.building', 'paymentMethod', 'receipt'])
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id));

        if (! empty($params['invoice_id'])) {
            $query->where('invoice_id', $params['invoice_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('note', 'like', '%'.$search.'%')
                    ->orWhere('payment_number', 'like', '%'.$search.'%')
                    ->orWhereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('paymentMethod', fn (Builder $methodQuery) => $methodQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest('payment_date')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findPayment(User $user, int $paymentId): Payment
    {
        return Payment::query()
            ->with(['invoice.contract.user.profile', 'invoice.contract.room.building', 'paymentMethod', 'receipt'])
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->findOrFail($paymentId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitPayment(User $user, array $data, ?UploadedFile $proof = null): Payment
    {
        $invoice = $this->findInvoice($user, (int) $data['invoice_id']);

        if (! in_array($invoice->status, ['issued', 'partial', 'overdue', 'unpaid'], true)) {
            throw new InvalidArgumentException('This invoice is not open for payment.');
        }

        if (! $proof) {
            throw new InvalidArgumentException('Payment proof is required.');
        }

        $payment = $this->paymentService->create([
            ...$data,
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        return $this->paymentService->uploadProof($payment, $proof)
            ->load(['invoice', 'paymentMethod']);
    }

    public function uploadPaymentProof(User $user, int $paymentId, UploadedFile $file): Payment
    {
        $payment = $this->findPayment($user, $paymentId);

        return $this->paymentService->uploadProof($payment, $file);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateReceipts(User $user, array $params): LengthAwarePaginator
    {
        $query = Receipt::query()
            ->with(['payment.invoice.contract.room.building', 'payment.paymentMethod'])
            ->where('status', 'issued')
            ->whereHas('payment.invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id));

        return $query->latest('issued_at')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findReceipt(User $user, int $receiptId): Receipt
    {
        return Receipt::query()
            ->with(['payment.invoice.contract.user.profile', 'payment.invoice.contract.room.building', 'payment.paymentMethod'])
            ->where('status', 'issued')
            ->whereHas('payment.invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->findOrFail($receiptId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notifications(User $user): array
    {
        $items = collect();

        $dueInvoices = $this->customerInvoiceQuery($user->id)
            ->whereIn('status', ['issued', 'partial', 'overdue', 'unpaid'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        foreach ($dueInvoices as $invoice) {
            $items->push([
                'id' => "invoice-{$invoice->id}",
                'type' => 'invoice',
                'title' => "Invoice {$invoice->invoice_number} requires payment",
                'message' => "Due on {$invoice->due_date?->toDateString()} · Total {$invoice->total_amount}",
                'status' => $invoice->status,
                'created_at' => $invoice->updated_at?->toDateTimeString(),
                'resource_id' => $invoice->id,
            ]);
        }

        $recentPayments = Payment::query()
            ->with('invoice')
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        foreach ($recentPayments as $payment) {
            $title = match ($payment->status) {
                'pending' => "Payment submitted for {$payment->invoice?->invoice_number}",
                'approved' => "Payment approved for {$payment->invoice?->invoice_number}",
                'rejected' => "Payment rejected for {$payment->invoice?->invoice_number}",
                default => "Payment update for {$payment->invoice?->invoice_number}",
            };

            $items->push([
                'id' => "payment-{$payment->id}",
                'type' => 'payment',
                'title' => $title,
                'message' => "Amount {$payment->amount} · Status {$payment->status}",
                'status' => $payment->status,
                'created_at' => $payment->updated_at?->toDateTimeString(),
                'resource_id' => $payment->invoice_id,
            ]);
        }

        $issuedReceipts = Receipt::query()
            ->with(['payment.invoice'])
            ->where('status', 'issued')
            ->whereHas('payment.invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->latest('issued_at')
            ->limit(10)
            ->get();

        foreach ($issuedReceipts as $receipt) {
            $items->push([
                'id' => "receipt-{$receipt->id}",
                'type' => 'receipt',
                'title' => "Receipt {$receipt->receipt_number} is ready",
                'message' => "Payment for {$receipt->payment?->invoice?->invoice_number} · Download your receipt",
                'status' => $receipt->status,
                'created_at' => $receipt->issued_at?->toDateTimeString() ?? $receipt->updated_at?->toDateTimeString(),
                'resource_id' => $receipt->id,
            ]);
        }

        $recentContracts = Contract::query()
            ->with('room')
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'active', 'completed'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        foreach ($recentContracts as $contract) {
            $items->push([
                'id' => "contract-{$contract->id}",
                'type' => 'contract',
                'title' => "Contract {$contract->contract_number} is {$contract->status}",
                'message' => ucfirst((string) $contract->type)." contract · Room {$contract->room?->room_number}",
                'status' => $contract->status,
                'created_at' => $contract->updated_at?->toDateTimeString(),
                'resource_id' => $contract->id,
            ]);
        }

        return $items
            ->sortByDesc('created_at')
            ->values()
            ->take(20)
            ->all();
    }

    public function contractDocumentResponse(User $user, Contract $contract, string $action): Response
    {
        $contract = $this->findContract($user, $contract->id);

        if ($contract->type === 'rent') {
            return match ($action) {
                'download' => $this->rentContractDocumentService->downloadResponse($contract),
                'export' => $this->rentContractDocumentService->exportResponse($contract),
                default => throw new InvalidArgumentException('Unsupported document action.'),
            };
        }

        return match ($action) {
            'download' => $this->saleContractDocumentService->downloadResponse($contract),
            'export' => $this->saleContractDocumentService->exportResponse($contract),
            default => throw new InvalidArgumentException('Unsupported document action.'),
        };
    }

    public function invoiceDocumentResponse(User $user, Invoice $invoice, string $action): Response
    {
        $invoice = $this->findInvoice($user, $invoice->id);

        return match ($action) {
            'download' => $this->invoiceDocumentService->downloadResponse($invoice),
            'export' => $this->invoiceDocumentService->exportResponse($invoice),
            default => throw new InvalidArgumentException('Unsupported document action.'),
        };
    }

    public function receiptDocumentResponse(User $user, Receipt $receipt, string $action): Response
    {
        $receipt = $this->findReceipt($user, $receipt->id);

        return match ($action) {
            'download' => $this->receiptDocumentService->downloadResponse($receipt),
            'export' => $this->receiptDocumentService->exportResponse($receipt),
            default => throw new InvalidArgumentException('Unsupported document action.'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentMethods(): array
    {
        return PaymentMethod::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
            ])
            ->all();
    }

    /**
     * @return Builder<Invoice>
     */
    private function customerInvoiceQuery(int $userId): Builder
    {
        return Invoice::query()
            ->whereHas('contract', fn (Builder $builder) => $builder->where('user_id', $userId))
            ->whereNotIn('status', ['draft']);
    }
}
