<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreInvoiceItemRequest extends BaseAdminFormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
