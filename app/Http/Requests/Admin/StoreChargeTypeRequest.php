<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreChargeTypeRequest extends BaseAdminFormRequest
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('charge_types', 'slug')],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
