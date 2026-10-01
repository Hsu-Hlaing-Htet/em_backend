<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends BaseAdminFormRequest
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
            'contract_id' => ['sometimes', 'integer', Rule::exists('contracts', 'id')],
            'utility_id' => ['nullable', 'integer', Rule::exists('utilities', 'id')],
            'type' => ['sometimes', 'string', Rule::in(['rent', 'sale', 'utility', 'other'])],
            'due_date' => ['sometimes', 'date'],
            'issued_date' => ['sometimes', 'nullable', 'date'],
            'billing_month' => ['sometimes', 'nullable', 'date'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['prohibited'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:invoice_items,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.previous_reading' => ['nullable', 'numeric'],
            'items.*.current_reading' => ['nullable', 'numeric'],
            'items.*.usage' => ['nullable', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
