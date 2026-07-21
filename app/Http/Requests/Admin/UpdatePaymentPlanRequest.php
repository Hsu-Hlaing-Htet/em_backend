<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdatePaymentPlanRequest extends BaseAdminFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'payment_type' => ['sometimes', 'string', Rule::in(['full', 'installment'])],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'interest_percentage' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
