<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreRoomRequest extends BaseAdminFormRequest
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
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')],
            'room_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'room_number')->where('building_id', $this->input('building_id')),
            ],
            'floor_number' => ['required', 'integer', 'min:0'],
            'area_sqft' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['sale', 'rent', 'both'])],
            'status' => ['required', 'string', Rule::in(['available', 'reserved', 'occupied', 'sold', 'maintenance'])],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'rent_price' => ['required', 'numeric', 'min:0'],
            'rent_deposit_price' => ['required', 'numeric', 'min:0'],
            'booking_deposit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
