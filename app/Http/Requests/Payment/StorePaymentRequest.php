<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_BANK_TRANSFER,
                Payment::METHOD_DEMO_ONLINE,
            ])],
            'reference_note' => ['nullable', 'string', 'max:255'],
            'slip_upload' => ['nullable', 'url'],
        ];
    }
}
