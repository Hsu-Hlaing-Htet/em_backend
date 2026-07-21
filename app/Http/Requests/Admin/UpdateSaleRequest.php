<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateSaleRequest extends BaseAdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')],
            'room_id' => ['sometimes', 'required', 'integer', Rule::exists('rooms', 'id')],
            'payment_plan_id' => ['nullable', 'integer', Rule::exists('payment_plans', 'id')],
            'sale_price' => ['nullable', 'numeric', 'gt:0'],
            'payment_type' => ['sometimes', 'required', 'string', Rule::in(['full', 'installment'])],
            'duration_months' => [
                'nullable',
                'integer',
                Rule::in([3, 6, 12]),
            ],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'remark' => ['nullable', 'string'],
            'sale_number' => ['prohibited'],
            'status' => ['prohibited'],
            'deposit_amount' => ['prohibited'],
            'paid_amount' => ['prohibited'],
            'created_by' => ['prohibited'],
            'submitted_by' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'activated_by' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'contract_id' => ['prohibited'],
        ];
    }
}
