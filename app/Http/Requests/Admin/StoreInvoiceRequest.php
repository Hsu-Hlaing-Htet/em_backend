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
            'type' => ['required', 'string', Rule::in(['rent', 'sale', 'utility', 'other'])],
            'due_date' => ['required', 'date'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
