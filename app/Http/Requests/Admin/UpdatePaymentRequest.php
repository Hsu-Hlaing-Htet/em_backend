<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends BaseAdminFormRequest
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
            'invoice_id' => ['sometimes', 'integer', Rule::exists('invoices', 'id')],
            'payment_method_id' => ['sometimes', 'integer', Rule::exists('payment_methods', 'id')],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'payment_date' => ['sometimes', 'date'],
            'note' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
