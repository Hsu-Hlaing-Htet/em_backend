<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateChargeTypeRequest extends BaseAdminFormRequest
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
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('charge_types', 'slug')->ignore($this->route('charge_type')),
            ],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
