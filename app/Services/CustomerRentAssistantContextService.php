<?php

namespace App\Services;

use App\Http\Resources\Admin\ContractResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Http\Resources\Admin\PaymentResource;
use App\Http\Resources\Admin\ReceiptResource;
use App\Http\Resources\Admin\ResidentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Builds authenticated customer context for Rosewood Rent Assistant.
 *
 * Loaded in-process by Laravel so FastAPI does not call back into Laravel
 * (avoids deadlock on single-worker `php artisan serve`).
 */
class CustomerRentAssistantContextService
{
    /** @var list<string> */
    private const BLOCKED_KEYS = [
        'note',
        'notes',
        'internal_note',
        'internal_notes',
        'verification_note',
        'verification_notes',
        'staff_comment',
        'staff_comments',
        'admin_note',
        'admin_notes',
        'approved_by',
        'approved_by_name',
        'created_by',
        'created_by_name',
        'sent_by',
        'sent_by_name',
        'approver',
        'creator',
        'sender',
        'proof_image_path',
        'proof_image_url',
        'avatar_path',
        'password',
        'remember_token',
    ];

    public function __construct(
        private readonly CustomerPortalService $customerPortalService,
    ) {}

    /**
     * @return array{
     *     dashboard: array<string, mixed>|null,
     *     profile: array<string, mixed>|null,
     *     contracts: list<array<string, mixed>>,
     *     invoices: list<array<string, mixed>>,
     *     payments: list<array<string, mixed>>,
     *     receipts: list<array<string, mixed>>,
     *     maintenance_requests: list<array<string, mixed>>,
     *     notifications: list<array<string, mixed>>,
     *     documents: array<string, mixed>
     * }
     */
    public function build(User $user, Request $request): array
    {
        $dashboard = $this->customerPortalService->dashboardSummary($user);
        unset($dashboard['recent_payments']);

        $profileUser = $this->customerPortalService->profile($user);
        $profile = (new ResidentResource($profileUser))->resolve($request);

        $contracts = ContractResource::collection(
            $this->customerPortalService->paginateContracts($user, ['per_page' => 100])->items()
        )->resolve($request);

        $invoicePaginator = $this->customerPortalService->paginateInvoices($user, ['per_page' => 100]);
        $invoices = collect($invoicePaginator->items())->map(function (Invoice $invoice) use ($request) {
            $resource = (new InvoiceResource($invoice))->resolve($request);
            $resource['paid_amount'] = $this->customerPortalService->invoicePaidAmount($invoice);

            return $resource;
        })->all();

        $paymentPaginator = $this->customerPortalService->paginatePayments($user, ['per_page' => 100]);
        $payments = collect($paymentPaginator->items())->map(function (Payment $payment) use ($request) {
            $resource = (new PaymentResource($payment))->resolve($request);
            $receipt = $payment->relationLoaded('receipt')
                ? $payment->receipt
                : $payment->receipt()->first();
            $resource['receipt_id'] = $receipt?->isDeliveredToCustomer() ? $receipt->id : null;

            return $resource;
        })->all();

        $receipts = ReceiptResource::collection(
            $this->customerPortalService->paginateReceipts($user, ['per_page' => 100])->items()
        )->resolve($request);

        $maintenance = MaintenanceRequestResource::collection(
            $this->customerPortalService->paginateMaintenanceRequests($user, ['per_page' => 100])->items()
        )->resolve($request);

        $notifications = $this->customerPortalService->notifications($user);

        $contracts = $this->normalizeContracts($this->sanitize($contracts));
        $invoices = $this->sanitize($invoices);
        $payments = $this->sanitize($payments);
        $receipts = $this->sanitize($receipts);

        return [
            'dashboard' => $this->sanitize($dashboard),
            'profile' => $this->sanitize($profile),
            'contracts' => $contracts,
            'invoices' => $invoices,
            'payments' => $payments,
            'receipts' => $receipts,
            'maintenance_requests' => $this->sanitize($maintenance),
            'notifications' => $this->sanitize($notifications),
            'documents' => $this->buildDocuments($contracts, $invoices, $receipts),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $contracts
     * @return list<array<string, mixed>>
     */
    private function normalizeContracts(array $contracts): array
    {
        return array_map(function (array $contract): array {
            if (! isset($contract['monthly_rent_amount'])) {
                $rent = $contract['estimated_monthly_payment']
                    ?? $contract['room_price']
                    ?? data_get($contract, 'room.rent_price');

                if ($rent !== null && $rent !== '') {
                    $contract['monthly_rent_amount'] = is_numeric($rent) ? (float) $rent : $rent;
                }
            }

            return $contract;
        }, $contracts);
    }

    /**
     * @param  list<array<string, mixed>>  $contracts
     * @param  list<array<string, mixed>>  $invoices
     * @param  list<array<string, mixed>>  $receipts
     * @return array<string, mixed>
     */
    private function buildDocuments(array $contracts, array $invoices, array $receipts): array
    {
        $utilityInvoices = array_values(array_filter($invoices, function (array $invoice): bool {
            $type = strtolower((string) ($invoice['invoice_type'] ?? $invoice['type'] ?? ''));

            return $type === 'utility';
        }));

        return [
            'contract_documents' => array_map(fn (array $c): array => [
                'contract_number' => $c['contract_number'] ?? $c['id'] ?? null,
                'status' => $c['status'] ?? null,
                'available' => true,
            ], $contracts),
            'invoice_documents' => array_map(fn (array $inv): array => [
                'invoice_number' => $inv['invoice_number'] ?? $inv['id'] ?? null,
                'type' => $inv['invoice_type'] ?? $inv['type'] ?? null,
                'status' => $inv['status'] ?? null,
                'available' => true,
            ], $invoices),
            'receipt_documents' => array_map(fn (array $r): array => [
                'receipt_number' => $r['receipt_number'] ?? $r['id'] ?? null,
                'status' => $r['display_status'] ?? $r['status'] ?? null,
                'available' => true,
            ], $receipts),
            'utility_bill_documents' => array_map(fn (array $inv): array => [
                'invoice_number' => $inv['invoice_number'] ?? $inv['id'] ?? null,
                'billing_period' => $inv['billing_period'] ?? $inv['billing_month'] ?? null,
                'status' => $inv['status'] ?? null,
                'available' => true,
            ], $utilityInvoices),
        ];
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            $cleaned = [];

            foreach ($value as $key => $item) {
                if (! $isList && is_string($key) && in_array(strtolower($key), self::BLOCKED_KEYS, true)) {
                    continue;
                }

                $cleaned[$key] = $this->sanitize($item);
            }

            return $cleaned;
        }

        return $value;
    }
}
