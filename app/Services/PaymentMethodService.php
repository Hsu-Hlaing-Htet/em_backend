<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaymentMethodService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = PaymentMethod::query();
        $this->applyListQuery($query, $params, ['name', 'slug', 'status']);

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
        return PaymentMethod::query()->create($this->prepareData($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->update($this->prepareData($data));

        return $paymentMethod->fresh();
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        $paymentMethod->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        unset($data['slug']);

        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
