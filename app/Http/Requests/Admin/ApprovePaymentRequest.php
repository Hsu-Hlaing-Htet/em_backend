<?php

namespace App\Http\Requests\Admin;

class ApprovePaymentRequest extends BaseAdminFormRequest
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
            // Optional when the payment already has a stored applied amount (e.g. Admin Cash).
            // Customer-submitted payments with null amount still require this field.
            'amount' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }
}
