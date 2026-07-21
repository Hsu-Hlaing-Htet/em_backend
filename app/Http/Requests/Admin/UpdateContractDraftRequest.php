<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateContractDraftRequest extends BaseAdminFormRequest
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
            'contract_total' => ['sometimes', 'numeric', 'gt:0'],
            'payment_type' => ['sometimes', 'string', Rule::in(['full', 'installment'])],
            'duration_months' => [
                Rule::requiredIf(fn () => $this->input('payment_type') === 'installment'),
                'nullable',
                'integer',
                Rule::in([3, 6, 12]),
            ],
            'start_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_day' => [
                Rule::requiredIf(fn () => $this->input('payment_type') === 'installment'),
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
            'remark' => ['nullable', 'string'],
            'contract_number' => ['prohibited'],
            'type' => ['prohibited'],
            'status' => ['prohibited'],
            'deposit_amount' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
