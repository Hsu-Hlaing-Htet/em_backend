<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateUtilityItemRequest extends BaseAdminFormRequest
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
            'utility_type_id' => ['sometimes', 'integer', Rule::exists('utility_types', 'id')],
            'previous_reading' => ['sometimes', 'numeric', 'min:0'],
            'current_reading' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
