<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateUtilityRequest extends BaseAdminFormRequest
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
            'room_id' => ['sometimes', 'integer', Rule::exists('rooms', 'id')],
            'billing_month' => ['sometimes', 'date'],
            'utility_items' => ['sometimes', 'array', 'min:1'],
            'utility_items.*.id' => ['nullable', 'integer', Rule::exists('utility_items', 'id')],
            'utility_items.*.utility_type_id' => ['required_with:utility_items', 'integer', Rule::exists('utility_types', 'id')],
            'utility_items.*.previous_reading' => ['required_with:utility_items', 'numeric', 'min:0'],
            'utility_items.*.current_reading' => ['required_with:utility_items', 'numeric', 'min:0'],
            'utility_items.*.unit_price' => ['required_with:utility_items', 'numeric', 'min:0'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
