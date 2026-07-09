<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StorePaymentRequest extends BaseAdminFormRequest
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
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
            'payment_date' => ['required', 'date'],
        ];
    }
}
