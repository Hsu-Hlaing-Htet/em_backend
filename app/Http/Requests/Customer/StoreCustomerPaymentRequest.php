<?php

namespace App\Http\Requests\Customer;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends FormRequest
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
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where('status', PaymentMethod::STATUS_ACTIVE)
                    ->whereNot('type', PaymentMethod::TYPE_CASH)
                    ->whereNull('deleted_at'),
            ],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'amount' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method_id.exists' => 'The selected payment method is not available for customer payments.',
        ];
    }
}
