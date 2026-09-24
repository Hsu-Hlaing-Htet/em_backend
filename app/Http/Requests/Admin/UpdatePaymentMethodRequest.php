<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPaymentMethodTypeFields;
use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends BaseAdminFormRequest
{
    use ValidatesPaymentMethodTypeFields;

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
            ...$this->paymentMethodTypeFieldRules(),
            'instructions' => ['nullable', 'string', 'max:5000'],
            'qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_qr_image' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', Rule::in(PaymentMethod::statuses())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->paymentMethodTypeFieldMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentMethodWalletPhoneInput();

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
