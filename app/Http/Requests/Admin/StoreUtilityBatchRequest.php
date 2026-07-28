<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreUtilityBatchRequest extends BaseAdminFormRequest
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
            'billing_month' => ['required', 'date'],
            'utility_type_id' => ['required', 'integer', Rule::exists('utility_types', 'id')],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'entries.*.current_reading' => ['required', 'numeric', 'min:0'],
            'entries.*.previous_reading' => ['nullable', 'numeric', 'min:0'],
            'entries.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
