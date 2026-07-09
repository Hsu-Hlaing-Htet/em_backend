<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends BaseAdminFormRequest
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
            'contract_id' => ['required', 'integer', Rule::exists('contracts', 'id')],
            'utility_id' => ['nullable', 'integer', Rule::exists('utilities', 'id')],
            'type' => ['nullable', 'string', Rule::in(['rent', 'utility', 'other'])],
            'issued_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'invoice_items' => ['sometimes', 'array'],
            'invoice_items.*.charge_type_id' => ['nullable', 'integer', Rule::exists('charge_types', 'id')],
            'invoice_items.*.description' => ['required', 'string', 'max:255'],
            'invoice_items.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
