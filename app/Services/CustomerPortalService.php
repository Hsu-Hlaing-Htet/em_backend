<?php

namespace App\Services;

use App\Http\Resources\Admin\PaymentResource;
use App\Models\Contract;
use App\Models\CustomerNotificationRead;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        private readonly MaintenanceRequestService $maintenanceRequestService,
    ) {}

    public function dashboardSummary(User $user): array
    {
        $activeContracts = Contract::query()
            ->where('user_id', $user->id)
            ->where('status', Contract::STATUS_ACTIVE)
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
            ->where('status', Payment::STATUS_APPROVED)
            ->count();

        $totalPaidAmount = (float) (clone $paymentQuery)
            ->where('status', Payment::STATUS_APPROVED)
            ->sum('amount');

        $recentPayments = (clone $paymentQuery)
            ->with([
                'invoice.contract.user.profile',
                'invoice.contract.room.building',
                'invoice.items.chargeType',
                'invoice.payments',
                'paymentMethod',
                'receipt',
            ])
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
        unset($data['password'], $data['password_confirmation'], $data['current_password']);

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
            ->whereIn('status', [
                Contract::STATUS_ACTIVE,
                Contract::STATUS_COMPLETED,
                Contract::STATUS_TERMINATED,
            ]);

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
            ->with(['user.profile', 'room.building', 'paymentPlan', 'creator', 'approver'])
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Contract::STATUS_ACTIVE,
                Contract::STATUS_COMPLETED,
                Contract::STATUS_TERMINATED,
            ])
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
            ->with([
                'contract.user.profile',
                'contract.room.building',
                'utility.items.utilityType',
                'items.chargeType',
                'payments.paymentMethod',
                'payments.receipt',
                'approver',
            ])
            ->findOrFail($invoiceId);
    }

    public function invoicePaidAmount(Invoice $invoice): float
    {
        return (float) $invoice->payments
            ->where('status', Payment::STATUS_APPROVED)
            ->sum('amount');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginatePayments(User $user, array $params): LengthAwarePaginator
    {
        $query = Payment::query()
            ->with([
                'invoice.contract.user.profile',
                'invoice.contract.room.building',
                'invoice.items.chargeType',
                'invoice.payments',
                'paymentMethod',
                'receipt',
            ])
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
                    ->orWhereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%'))
                    ->orWhereHas('paymentMethod', fn (Builder $methodQuery) => $methodQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest('payment_date')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findPayment(User $user, int $paymentId): Payment
    {
        return Payment::query()
            ->with([
                'invoice.contract.user.profile',
                'invoice.contract.room.building',
                'invoice.items.chargeType',
                'invoice.payments',
                'paymentMethod',
                'receipt',
            ])
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->findOrFail($paymentId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitPayment(User $user, array $data, ?UploadedFile $proof = null): Payment
    {
        return DB::transaction(function () use ($user, $data, $proof): Payment {
            $invoice = $this->findInvoice($user, (int) $data['invoice_id']);

            /** @var \App\Models\Invoice $lockedInvoice */
            $lockedInvoice = \App\Models\Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedInvoice->status, ['issued', 'partial', 'overdue', 'unpaid'], true)) {
                throw new InvalidArgumentException('This invoice is not open for payment.');
            }

            if (! $proof) {
                throw new InvalidArgumentException('Payment proof is required.');
            }

            unset($data['amount'], $data['proof'], $data['status'], $data['approved_by'], $data['approved_at']);

            $payment = $this->paymentService->create([
                ...$data,
                'invoice_id' => $lockedInvoice->id,
                'amount' => null,
                'created_by' => $user->id,
                'status' => 'pending',
            ]);

            return $this->paymentService->uploadProof($payment, $proof);
        });
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
            ->with([
                'payment.invoice.contract.user.profile',
                'payment.invoice.contract.room.building',
                'payment.invoice.items.chargeType',
                'payment.invoice.payments',
                'payment.paymentMethod',
            ])
            ->deliveredToCustomer()
            ->whereHas('payment.invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id));

        return $query->latest('issued_at')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findReceipt(User $user, int $receiptId): Receipt
    {
        return Receipt::query()
            ->with(['payment.invoice.contract.user.profile', 'payment.invoice.contract.room.building', 'payment.paymentMethod'])
            ->deliveredToCustomer()
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
            ->with(['invoice', 'receipt'])
            ->whereHas('invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        foreach ($recentPayments as $payment) {
            $receiptId = $payment->relationLoaded('receipt')
                ? $payment->receipt?->id
                : $payment->receipt()->value('id');

            if ($payment->status === 'approved') {
                $items->push([
                    'id' => "payment-{$payment->id}",
                    'type' => 'payment',
                    'title' => 'Payment Approved',
                    'message' => 'Your payment has been approved. Your receipt is now available in your Customer Portal.',
                    'status' => $payment->status,
                    'created_at' => $payment->updated_at?->toDateTimeString(),
                    'resource_id' => $receiptId ?: $payment->id,
                    'receipt_id' => $receiptId,
                    'payment_id' => $payment->id,
                ]);

                continue;
            }

            if ($payment->status === 'rejected') {
                $items->push([
                    'id' => "payment-{$payment->id}",
                    'type' => 'payment',
                    'title' => 'Payment Rejected',
                    'message' => 'Your payment has been rejected. Please check the details and submit again.',
                    'status' => $payment->status,
                    'created_at' => $payment->updated_at?->toDateTimeString(),
                    'resource_id' => $payment->id,
                    'payment_id' => $payment->id,
                ]);

                continue;
            }

            $items->push([
                'id' => "payment-{$payment->id}",
                'type' => 'payment',
                'title' => "Payment submitted for {$payment->invoice?->invoice_number}",
                'message' => "Amount {$payment->amount} · Status {$payment->status}",
                'status' => $payment->status,
                'created_at' => $payment->updated_at?->toDateTimeString(),
                'resource_id' => $payment->id,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
            ]);
        }

        $issuedReceipts = Receipt::query()
            ->with(['payment.invoice'])
            ->deliveredToCustomer()
            ->whereHas('payment.invoice.contract', fn (Builder $builder) => $builder->where('user_id', $user->id))
            ->latest('sent_at')
            ->limit(10)
            ->get();

        foreach ($issuedReceipts as $receipt) {
            $items->push([
                'id' => "receipt-{$receipt->id}",
                'type' => 'receipt',
                'title' => "Receipt {$receipt->receipt_number} is ready",
                'message' => "Payment for {$receipt->payment?->invoice?->invoice_number} · Download your receipt",
                'status' => $receipt->status,
                'created_at' => $receipt->sent_at?->toDateTimeString() ?? $receipt->issued_at?->toDateTimeString() ?? $receipt->updated_at?->toDateTimeString(),
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

        $sorted = $items
            ->sortByDesc('created_at')
            ->values()
            ->take(20)
            ->values();

        $keys = $sorted->pluck('id')->filter()->values()->all();

        $reads = empty($keys)
            ? collect()
            : CustomerNotificationRead::query()
                ->where('user_id', $user->id)
                ->whereIn('notification_key', $keys)
                ->get(['notification_key', 'read_at'])
                ->keyBy('notification_key');

        return $sorted
            ->map(function (array $item) use ($reads): array {
                $readAt = $reads->get($item['id'])?->read_at;
                $item['read_at'] = $readAt?->toDateTimeString();

                return $item;
            })
            ->all();
    }

    /**
     * @return array{id: string, read_at: string|null}
     */
    public function markNotificationAsRead(User $user, string $notificationKey): array
    {
        $notificationKey = trim($notificationKey);

        if (! preg_match('/^(invoice|payment|receipt|contract|utility|maintenance)-\d+$/', $notificationKey)) {
            throw new InvalidArgumentException('Invalid notification key.');
        }

        $read = CustomerNotificationRead::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_key' => $notificationKey,
            ],
            [
                'read_at' => now(),
            ]
        );

        return [
            'id' => $notificationKey,
            'read_at' => $read->read_at?->toDateTimeString(),
        ];
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
        return app(PaymentMethodService::class)->customerAvailableMethods();
    }

    /**
     * Rooms linked to the customer's active/approved contracts.
     *
     * @return list<array<string, mixed>>
     */
    public function maintenanceRooms(User $user): array
    {
        return $this->eligibleMaintenanceRooms($user)
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'building_name' => $room->building?->building_name,
                'label' => trim(($room->building?->building_name ? $room->building->building_name.' · ' : '').$room->room_number),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateMaintenanceRequests(User $user, array $params): LengthAwarePaginator
    {
        $query = MaintenanceRequest::query()
            ->with(['room.building', 'user', 'approver'])
            ->where('user_id', $user->id);

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhereHas('room', fn (Builder $roomQuery) => $roomQuery->where('room_number', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest('id')->paginate((int) ($params['per_page'] ?? 10));
    }

    public function findMaintenanceRequest(User $user, int $maintenanceRequestId): MaintenanceRequest
    {
        return MaintenanceRequest::query()
            ->with(['room.building', 'user', 'creator', 'approver'])
            ->where('user_id', $user->id)
            ->findOrFail($maintenanceRequestId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMaintenanceRequest(User $user, array $data): MaintenanceRequest
    {
        $roomIds = $this->eligibleMaintenanceRoomIds($user);

        if (! in_array((int) $data['room_id'], $roomIds, true)) {
            throw new InvalidArgumentException('Selected room is not linked to an active approved contract.');
        }

        return $this->maintenanceRequestService->create([
            'room_id' => (int) $data['room_id'],
            'user_id' => $user->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'description' => $data['description'] ?? null,
        ])->load(['room.building', 'user', 'creator', 'approver']);
    }

    /**
     * @return list<int>
     */
    public function eligibleMaintenanceRoomIds(User $user): array
    {
        return Contract::query()
            ->where('user_id', $user->id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->pluck('room_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Room>
     */
    private function eligibleMaintenanceRooms(User $user): Collection
    {
        $roomIds = $this->eligibleMaintenanceRoomIds($user);

        if ($roomIds === []) {
            return collect();
        }

        return Room::query()
            ->with('building')
            ->whereIn('id', $roomIds)
            ->orderBy('room_number')
            ->get();
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
