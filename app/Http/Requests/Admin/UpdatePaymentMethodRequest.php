<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends BaseAdminFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(PaymentMethod::types())],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_qr_image' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', Rule::in(PaymentMethod::statuses())],
            'is_customer_visible' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_customer_visible')) {
            $this->merge([
                'is_customer_visible' => filter_var(
                    $this->input('is_customer_visible'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        if ($this->has('remove_qr_image')) {
            $this->merge([
                'remove_qr_image' => filter_var(
                    $this->input('remove_qr_image'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }
}
