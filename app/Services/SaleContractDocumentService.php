<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Http\Response;

class SaleContractDocumentService
{
    public function __construct(
        private readonly TypedContractDocumentService $typedContractDocumentService,
    ) {}

    private function service(): TypedContractDocumentService
    {
        return $this->typedContractDocumentService->for('sale');
    }

    public function findDraft(int $id): Contract
    {
        return $this->service()->findDraft($id);
    }

    public function findApproved(int $id): Contract
    {
        return $this->service()->findActive($id);
    }

    public function renderHtml(Contract $contract): string
    {
        return $this->service()->renderHtml($contract);
    }

    public function downloadResponse(Contract $contract): Response
    {
        return $this->service()->downloadResponse($contract);
    }

    public function exportResponse(Contract $contract): Response
    {
        return $this->service()->exportResponse($contract);
    }

    /**
     * @param  array{email?: string|null}  $data
     */
    public function sendEmail(Contract $contract, array $data): void
    {
        $this->service()->sendEmail($contract, $data);
    }
}
