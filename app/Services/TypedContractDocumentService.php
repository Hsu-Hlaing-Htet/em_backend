<?php

namespace App\Services;

use App\Models\Contract;
use App\Services\Concerns\ServesHtmlDocument;
use App\Support\ContractDocumentProfile;
use App\Support\ContractDraftProfile;
use App\Support\DocumentFilename;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class TypedContractDocumentService
{
    use ServesHtmlDocument;

    private ContractDocumentProfile $profile;

    public function __construct(
        private readonly TypedContractDraftService $typedContractDraftService,
    ) {
        $this->profile = ContractDocumentProfile::sale();
    }

    public function for(string $type): self
    {
        $service = clone $this;
        $service->profile = ContractDocumentProfile::fromType($type);

        return $service;
    }

    public function findDraft(int $id): Contract
    {
        return $this->draftService()->find($id);
    }

    public function findActive(int $id): Contract
    {
        return $this->draftService()->findActive($id);
    }

    public function renderHtml(Contract $contract): string
    {
        $contract->loadMissing([
            'user.profile',
            'secondUser.profile',
            'room.building',
            'paymentPlan',
            'creator',
            'approver',
        ]);

        return view($this->profile->view, [
            'document' => $this->buildDocumentData($contract),
        ])->render();
    }

    public function downloadResponse(Contract $contract, ?string $filename = null): Response
    {
        return $this->downloadPdfResponse(
            $this->renderHtml($contract),
            $filename ?? $this->downloadFilename($contract),
        );
    }

    public function exportResponse(Contract $contract): Response
    {
        return $this->exportHtmlResponse(
            $this->renderHtml($contract),
            $this->htmlFilename($contract),
        );
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Contract $contract, array $data): void
    {
        $contract->loadMissing(['user.profile', 'secondUser.profile', 'room']);
        $emails = isset($data['email']) && trim((string) $data['email']) !== ''
            ? [trim((string) $data['email'])]
            : $contract->partyEmails();

        if ($emails === []) {
            throw new InvalidArgumentException('Customer email is required to send the contract document.');
        }

        $filename = $this->filename($contract);
        $pdf = $this->renderPdfBinary($this->renderHtml($contract));
        $mailClass = $this->profile->mailClass;

        foreach ($emails as $email) {
            Mail::to($email)->send(new $mailClass($contract, $pdf, $filename));
        }
    }

    /**
     * @param  array{html?: string|null}  $data
     */
    public function sendEmailToContractCustomer(Contract $contract, array $data = []): string
    {
        $contract->loadMissing(['user.profile', 'secondUser.profile', 'room']);
        $emails = $contract->partyEmails();

        if ($emails === []) {
            throw new InvalidArgumentException('Customer email is required to send the contract document.');
        }

        $pdf = isset($data['html']) && trim((string) $data['html']) !== ''
            ? $this->documentPdfService()->renderPreviewPdf((string) $data['html'])
            : $this->renderPdfBinary($this->renderHtml($contract));
        $mailClass = $this->profile->mailClass;

        foreach ($emails as $email) {
            Mail::to($email)->send(new $mailClass(
                $contract,
                $pdf,
                $this->downloadFilename($contract),
                $this->customerContractUrl($contract),
            ));
        }

        return implode(', ', $emails);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentData(Contract $contract): array
    {
        $priceColumn = $contract->type === 'rent' ? 'rent_price' : 'sale_price';
        $roomPrice = (float) ($contract->room?->{$priceColumn} ?? $contract->contract_total);
        $depositAmount = (float) $contract->deposit_amount;
        $contractTotal = (float) $contract->contract_total;
        $isRent = $contract->type === 'rent';
        $remainingBalance = $isRent ? $contractTotal : max($contractTotal - $depositAmount, 0);
        $interestPercentage = (float) ($contract->paymentPlan?->interest_percentage ?? 0);
        $interestAmount = $remainingBalance * ($interestPercentage / 100);
        $totalInstallmentAmount = $contract->payment_type === 'installment'
            ? $remainingBalance + $interestAmount
            : 0;
        $estimatedMonthlyPayment = $isRent
            ? $roomPrice
            : ($contract->payment_type === 'installment' && $contract->duration_months
                ? (int) ceil($totalInstallmentAmount / $contract->duration_months)
                : 0);
        $isInstallment = $contract->payment_type === 'installment';
        $activeProfile = ContractDraftProfile::fromType($contract->type);
        $isActive = $contract->status === $activeProfile->activeStatus;

        $contractFields = [
            ['label' => 'Contract Number', 'value' => $contract->contract_number],
            ['label' => 'Payment Plan', 'value' => $contract->paymentPlan?->name ?? '-'],
            ['label' => 'Payment Type', 'value' => $this->paymentTypeLabel($contract->payment_type)],
            ['label' => 'Contract Duration', 'value' => $contract->duration_months ? $contract->duration_months.' months' : '-'],
            ['label' => 'Contract Total', 'value' => $this->formatCurrency($contractTotal)],
            ['label' => 'Commencement Date', 'value' => optional($contract->start_date)->toDateString() ?? '-'],
            ['label' => 'Billing Day', 'value' => $contract->start_date
                ? 'Day '.$contract->start_date->day.' of each month'
                : '-'],
            ['label' => 'Contract Status', 'value' => $this->statusLabel($contract->status)],
        ];

        if ($contract->type === 'rent') {
            array_splice($contractFields, 6, 0, [[
                'label' => 'End Date',
                'value' => optional($contract->end_date)->toDateString() ?? '-',
            ]]);
        }

        $leaseEndDate = $this->contractEndDate($contract);
        $financialRows = [
            ['label' => $this->profile->priceLabel, 'value' => $this->formatCurrency($roomPrice)],
            ['label' => $this->profile->depositLabel, 'value' => $this->formatCurrency($depositAmount)],
            ['label' => $isRent ? 'Lease Start' : 'Commencement Date', 'value' => optional($contract->start_date)->toDateString() ?? '-'],
            ['label' => $isRent ? 'Lease End' : 'End Date', 'value' => $leaseEndDate],
            ['label' => 'Duration', 'value' => $contract->duration_months ? $contract->duration_months.' months' : '-'],
            ['label' => 'Payment Due Day', 'value' => $contract->start_date ? 'Day '.$contract->start_date->day.' of each month' : '-'],
            ['label' => 'Payment Plan', 'value' => $contract->paymentPlan?->name ?? '-'],
            ['label' => 'Payment Method', 'value' => $this->paymentTypeLabel($contract->payment_type)],
            ['label' => 'Total', 'value' => $this->formatCurrency($contractTotal)],
        ];

        $authorization = [
            'preparedBy' => $contract->creator?->name ?? '-',
            'preparedAt' => optional($contract->created_at)->format('Y-m-d H:i') ?? '-',
            'approvedBy' => $isActive ? ($contract->approver?->name ?? '') : '',
            'approvedAt' => $isActive ? (optional($contract->approved_at)->format('Y-m-d H:i') ?? '') : '',
        ];
        $authorizationRows = array_values(array_filter([
            [
                'label' => 'Prepared by',
                'value' => $this->authorizationLine($authorization['preparedBy'], $authorization['preparedAt']),
            ],
            [
                'label' => 'Approved by',
                'value' => $this->authorizationLine($authorization['approvedBy'], $authorization['approvedAt']),
            ],
        ], fn (array $row): bool => $this->hasDocumentValue($row['value'])));

        $partyLabel = $contract->type === 'rent' ? 'Tenant' : 'Owner';
        $secondUser = $contract->secondUser;
        $signatures = [
            [
                'name' => $contract->user?->name ?? '________________',
                'label' => $this->profile->tenantSignatureLabel.($secondUser ? ' (1)' : ''),
            ],
        ];

        if ($secondUser) {
            $signatures[] = [
                'name' => $secondUser->name ?? '________________',
                'label' => $this->profile->tenantSignatureLabel.' (2)',
            ];
        }

        $signatures[] = [
            'name' => $contract->approver?->name ?? $contract->creator?->name ?? '________________',
            'label' => 'Company Representative',
        ];

        return [
            'header' => [
                'contractNo' => $contract->contract_number,
                'issuedDate' => optional($contract->created_at)->format('Y-m-d H:i') ?? '-',
            ],
            'customer' => [
                ['label' => 'Full Name', 'value' => $contract->user?->name ?? '-'],
                ['label' => 'NRC / ID', 'value' => $contract->user?->profile?->nrc ?? '-'],
                ['label' => 'Phone', 'value' => $contract->user?->profile?->phone ?? '-'],
                ['label' => 'Address', 'value' => $contract->user?->profile?->address ?? '-'],
                ['label' => 'Email', 'value' => $contract->user?->email ?? '-'],
            ],
            'secondCustomer' => $secondUser ? [
                ['label' => 'Full Name', 'value' => $secondUser->name ?? '-'],
                ['label' => 'NRC / ID', 'value' => $secondUser->profile?->nrc ?? '-'],
                ['label' => 'Phone', 'value' => $secondUser->profile?->phone ?? '-'],
                ['label' => 'Address', 'value' => $secondUser->profile?->address ?? '-'],
                ['label' => 'Email', 'value' => $secondUser->email ?? '-'],
            ] : null,
            'partyLabel' => $partyLabel,
            'company' => [
                ['label' => 'Company Name', 'value' => 'Rosewood Royale Residences'],
                ['label' => 'Registration', 'value' => 'Company Reg. No. RR-2020-001'],
                ['label' => 'Address', 'value' => 'No. 12, Kabar Aye Pagoda Road, Bahan Township, Yangon'],
                ['label' => 'Phone', 'value' => '+95 9 123 456 789'],
                ['label' => 'Email', 'value' => 'contracts@rosewoodroyale.com'],
                ['label' => 'Website', 'value' => 'www.rosewoodroyale.com'],
            ],
            'property' => [
                ['label' => 'Building', 'value' => $contract->room?->building?->building_name ?? '-'],
                ['label' => 'Room / Unit', 'value' => $contract->room?->room_number ?? '-'],
                ['label' => $this->profile->priceLabel, 'value' => $this->formatCurrency($roomPrice)],
                ['label' => $this->profile->depositLabel, 'value' => $this->formatCurrency($depositAmount)],
            ],
            'propertyLocation' => [
                ['label' => 'Residence', 'value' => $contract->room?->building?->building_name ?? '-'],
                ['label' => 'Unit', 'value' => $contract->room?->room_number ?? '-'],
                ['label' => 'Address', 'value' => $contract->user?->profile?->address ?? '-'],
            ],
            'contract' => $contractFields,
            'financial' => $financialRows,
            'payment' => $isInstallment ? [
                ['label' => 'Contract Total', 'value' => $this->formatCurrency($contractTotal)],
                ['label' => 'Deposit', 'value' => $this->formatCurrency($depositAmount)],
                ['label' => 'Interest (%)', 'value' => number_format($interestPercentage, 2).'%'],
                ['label' => 'Remaining Balance', 'value' => $this->formatCurrency($remainingBalance)],
                ['label' => 'Total Installment Amount', 'value' => $this->formatCurrency($totalInstallmentAmount)],
                ['label' => 'Duration', 'value' => $contract->duration_months.' months'],
                ['label' => 'Estimated Monthly Payment', 'value' => $this->formatCurrency($estimatedMonthlyPayment)],
            ] : [
                ['label' => 'Contract Total', 'value' => $this->formatCurrency($contractTotal)],
                ['label' => 'Deposit', 'value' => $this->formatCurrency($depositAmount)],
                ['label' => 'Payment Type', 'value' => $this->paymentTypeLabel($contract->payment_type)],
            ],
            'installment' => $isInstallment ? [
                'remainingAfterDeposit' => $this->formatCurrency($remainingBalance),
                'duration' => $contract->duration_months.' months',
                'monthlyPayment' => $this->formatCurrency($estimatedMonthlyPayment),
            ] : null,
            'approval' => $isActive ? [
                ['label' => 'Prepared By', 'value' => $contract->creator?->name ?? '-'],
                ['label' => 'Created Date', 'value' => optional($contract->created_at)->format('Y-m-d H:i') ?? '-'],
                ['label' => 'Approved By', 'value' => $contract->approver?->name ?? 'Pending'],
                ['label' => 'Approved Date', 'value' => optional($contract->approved_at)->format('Y-m-d H:i') ?? 'Pending'],
            ] : [
                ['label' => 'Prepared By', 'value' => $contract->creator?->name ?? '-'],
                ['label' => 'Created Date', 'value' => optional($contract->created_at)->format('Y-m-d H:i') ?? '-'],
            ],
            'authorization' => $authorization,
            'authorizationRows' => $authorizationRows,
            'remarks' => trim((string) $contract->remark) !== '' ? $contract->remark : 'No additional remarks.',
            'signatures' => $signatures,
        ];
    }

    private function draftService(): TypedContractDraftService
    {
        return $this->typedContractDraftService->for($this->profile->type);
    }

    private function filename(Contract $contract): string
    {
        $contract->loadMissing(['room']);

        if ($contract->type === 'rent') {
            return DocumentFilename::rentContract(
                $contract->start_date ?? $contract->created_at,
                $contract->room?->room_number,
                $contract->contract_number,
            );
        }

        return DocumentFilename::saleContract(
            $contract->start_date ?? $contract->created_at,
            $contract->room?->room_number,
            $contract->contract_number,
        );
    }

    private function downloadFilename(Contract $contract): string
    {
        $type = $contract->type === 'rent' ? 'Rent' : 'Sale';
        $prefix = $contract->type === 'rent' ? 'R' : 'S';
        $contractNumber = $contract->contract_number ?: sprintf('%s-%06d', $prefix, $contract->id);

        return "Rosewood_Royale_{$type}_Contract_{$contractNumber}.pdf";
    }

    private function customerContractUrl(Contract $contract): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/customer/contracts/'.$contract->id;
    }

    private function contractEndDate(Contract $contract): string
    {
        if ($contract->end_date) {
            return $contract->end_date->toDateString();
        }

        if (! $contract->start_date || ! $contract->duration_months) {
            return '-';
        }

        return $contract->start_date
            ->copy()
            ->addMonths((int) $contract->duration_months)
            ->subDay()
            ->toDateString();
    }

    private function htmlFilename(Contract $contract): string
    {
        return ($contract->contract_number ?: $this->profile->defaultFilename).'.html';
    }

    private function formatCurrency(float $amount): string
    {
        return 'MMK '.number_format($amount, 0);
    }

    private function paymentTypeLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'full' => 'Full Payment',
            'installment' => 'Installment',
            default => $paymentType ?? '-',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'active' => 'Active',
            'completed' => 'Completed',
            'terminated' => 'Terminated',
            'rejected' => 'Rejected',
            default => $status ?? '-',
        };
    }

    private function authorizationLine(?string $name, ?string $date): string
    {
        $parts = [];

        if ($this->hasDocumentValue($name) && ! ctype_digit(trim((string) $name))) {
            $parts[] = trim((string) $name);
        }

        if ($this->hasDocumentValue($date)) {
            $parts[] = trim((string) $date);
        }

        return implode(' · ', $parts);
    }

    private function hasDocumentValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return ! in_array(trim($value), ['', '-', '—'], true);
        }

        return true;
    }
}
