<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreUtilityItemRequest extends BaseAdminFormRequest
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
            'utility_type_id' => ['required', 'integer', Rule::exists('utility_types', 'id')],
            'previous_reading' => ['required', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
