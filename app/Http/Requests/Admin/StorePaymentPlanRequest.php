<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StorePaymentPlanRequest extends BaseAdminFormRequest
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
            'payment_type' => ['required', 'string', Rule::in(['full', 'installment'])],
            'duration_months' => ['nullable', 'integer', 'min:1'],
            'interest_percentage' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
