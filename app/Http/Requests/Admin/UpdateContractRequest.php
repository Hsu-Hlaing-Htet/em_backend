<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateContractRequest extends BaseAdminFormRequest
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
            'user_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'room_id' => ['sometimes', 'integer', Rule::exists('rooms', 'id')],
            'payment_plan_id' => ['nullable', 'integer', Rule::exists('payment_plans', 'id')],
            'contract_total' => ['nullable', 'numeric', 'min:0'],
            'type' => ['sometimes', 'string', Rule::in(['rent', 'sale'])],
            'payment_type' => ['nullable', 'string', Rule::in(['full', 'installment'])],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'remark' => ['nullable', 'string'],
        ];
    }
}
