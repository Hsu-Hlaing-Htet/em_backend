<?php

namespace App\Services;

use App\Models\PaymentPlan;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentPlanService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = PaymentPlan::query();
        $this->applyListQuery($query, $params, ['name', 'payment_type', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): PaymentPlan
    {
        return PaymentPlan::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentPlan
    {
        return PaymentPlan::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentPlan $paymentPlan, array $data): PaymentPlan
    {
        $paymentPlan->update($data);

        return $paymentPlan->fresh();
    }

    public function delete(PaymentPlan $paymentPlan): void
    {
        $paymentPlan->delete();
    }
}
