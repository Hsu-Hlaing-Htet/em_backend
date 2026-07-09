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
        $utility = $this->route('utility');

        return [
            'room_id' => ['sometimes', 'integer', Rule::exists('rooms', 'id')],
            'billing_month' => [
                'sometimes',
                'date',
                Rule::unique('utilities', 'billing_month')
                    ->where(fn ($query) => $query->where('room_id', $this->input('room_id', $utility?->room_id)))
                    ->ignore($utility),
            ],
            'utility_items' => ['sometimes', 'array'],
            'utility_items.*.id' => ['nullable', 'integer', Rule::exists('utility_items', 'id')],
            'utility_items.*.utility_type_id' => ['required', 'integer', Rule::exists('utility_types', 'id')],
            'utility_items.*.previous_reading' => ['required', 'numeric', 'min:0'],
            'utility_items.*.current_reading' => ['required', 'numeric', 'min:0'],
            'utility_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
