<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SaleContractDraftService
{
    public function __construct(
        private readonly TypedContractDraftService $typedContractDraftService,
    ) {}

    private function service(): TypedContractDraftService
    {
        return $this->typedContractDraftService->for('sale');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        return $this->service()->paginate($params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginateApproved(array $params): LengthAwarePaginator
    {
        return $this->service()->paginateActive($params);
    }

    public function find(int $id): Contract
    {
        return $this->service()->find($id);
    }

    public function findApproved(int $id): Contract
    {
        return $this->service()->findActive($id);
    }

    public function findForDeletion(int $id): Contract
    {
        return $this->service()->findForDeletion($id);
    }

    public function approve(Contract $contract): Contract
    {
        return $this->service()->approve($contract);
    }

    public function reject(Contract $contract, ?string $reason = null): Contract
    {
        return $this->service()->reject($contract, $reason);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contract
    {
        return $this->service()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contract $contract, array $data): Contract
    {
        return $this->service()->update($contract, $data);
    }

    public function delete(Contract $contract): void
    {
        $this->service()->delete($contract);
    }

    public function cancel(Contract $contract, string $reason): Contract
    {
        return $this->service()->cancel($contract, $reason);
    }

    public function generateContractNumber(): string
    {
        return $this->service()->generateContractNumber();
    }
}
