<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
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
            // Validated further in InvoiceLateFeeService (active rule / none / required).
            'late_fee_selection' => ['nullable'],
            'late_fee_rule_id' => ['nullable', 'integer', 'exists:late_fees,id'],
            'late_fee_waived' => ['nullable', 'boolean'],
        ];
    }
}
