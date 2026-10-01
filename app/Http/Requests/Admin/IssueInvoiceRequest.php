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
            // Validated further in InvoiceLateFeeService (active rule required).
            'late_fee_selection' => ['nullable'],
            'late_fee_rule_id' => ['nullable', 'integer', 'exists:late_fees,id'],
            'late_fee_waived' => ['nullable', 'boolean'],

            // Invoice-specific review fields allowed on Confirm.
            'due_date' => ['sometimes', 'date'],

            // System-controlled on Confirm (set at issue / already on draft).
            'issued_date' => ['prohibited'],
            'billing_month' => ['prohibited'],

            // Safe line corrections only — totals/usage/amount are server-calculated.
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:invoice_items,id'],
            'items.*.current_reading' => ['sometimes', 'nullable', 'numeric'],
            'items.*.unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.previous_reading' => ['prohibited'],
            'items.*.usage' => ['prohibited'],
            'items.*.amount' => ['prohibited'],
            'items.*.description' => ['prohibited'],

            // Source ownership / property fields are not editable on Confirm.
            'contract_id' => ['prohibited'],
            'utility_id' => ['prohibited'],
            'type' => ['prohibited'],
            'building_id' => ['prohibited'],
            'room_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'late_fee' => ['prohibited'],
            'total_amount' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'issued_date.prohibited' => 'Issue date is system-controlled and cannot be changed during confirm.',
            'billing_month.prohibited' => 'Billing period cannot be changed during confirm.',
            'contract_id.prohibited' => 'Invoice customer/contract cannot be changed during confirm.',
            'utility_id.prohibited' => 'Invoice utility source cannot be changed during confirm.',
            'type.prohibited' => 'Invoice type cannot be changed during confirm.',
            'building_id.prohibited' => 'Invoice building cannot be changed during confirm.',
            'room_id.prohibited' => 'Invoice room cannot be changed during confirm.',
            'user_id.prohibited' => 'Invoice customer ownership cannot be changed during confirm.',
            'items.*.previous_reading.prohibited' => 'Previous unit is read-only and cannot be changed during confirm.',
            'items.*.usage.prohibited' => 'Usage is calculated and cannot be set during confirm.',
            'items.*.amount.prohibited' => 'Line amount is calculated and cannot be set during confirm.',
            'items.*.description.prohibited' => 'Line description cannot be changed during confirm.',
            'late_fee.prohibited' => 'Late fee amount is calculated and cannot be set during confirm.',
            'total_amount.prohibited' => 'Invoice total is calculated and cannot be set during confirm.',
        ];
    }
}
