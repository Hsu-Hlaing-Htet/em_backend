<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_id' => ['required', 'exists:contracts,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'first_due_date' => ['required', 'date'],
            'number_of_months' => ['nullable', 'integer', 'min:1'],
            'monthly_due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'mode' => ['required', 'in:full,installment,rent'],
        ];
    }
}
