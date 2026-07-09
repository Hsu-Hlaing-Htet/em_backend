<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentMethodService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = PaymentMethod::query();
        $this->applyListQuery($query, $params, ['name', 'type', 'status']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): PaymentMethod
    {
        return PaymentMethod::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->update($data);

        return $paymentMethod->fresh();
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        $paymentMethod->delete();
    }
}
