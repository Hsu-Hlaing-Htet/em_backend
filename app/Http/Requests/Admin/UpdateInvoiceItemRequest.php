<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateInvoiceItemRequest extends BaseAdminFormRequest
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
            'charge_type_id' => ['nullable', 'integer', Rule::exists('charge_types', 'id')],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
