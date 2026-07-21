<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreUtilityRequest extends BaseAdminFormRequest
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
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'billing_month' => ['required', 'date'],
            'utility_items' => ['required', 'array', 'min:1'],
            'utility_items.*.utility_type_id' => ['required', 'integer', Rule::exists('utility_types', 'id')],
            'utility_items.*.previous_reading' => ['required', 'numeric', 'min:0'],
            'utility_items.*.current_reading' => ['required', 'numeric', 'min:0'],
            'utility_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
