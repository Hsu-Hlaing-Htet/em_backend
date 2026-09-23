<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
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
            'building_id' => ['required', 'integer', 'exists:buildings,id,status,active'],
            'room_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'room_number')
                    ->where(fn ($query) => $query->where('building_id', $this->input('building_id'))),
            ],
            'floor_number' => ['required', 'integer', 'min:0'],
            'width_ft' => ['nullable', 'numeric', 'min:0'],
            'length_ft' => ['nullable', 'numeric', 'min:0'],
            'area_sqft' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in([Room::TYPE_SALE, Room::TYPE_RENT, Room::TYPE_BOTH])],
            'status' => ['required', 'string', Rule::in([
                Room::STATUS_AVAILABLE,
                Room::STATUS_OCCUPIED,
                Room::STATUS_SOLD,
            ])],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'rent_price' => ['required', 'numeric', 'min:0'],
            'rent_deposit_price' => ['required', 'numeric', 'min:0'],
            'booking_deposit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $roomNumber = trim((string) $this->input('room_number', ''));

        return [
            'room_number.unique' => $roomNumber !== ''
                ? "Room {$roomNumber} already exists in this building."
                : 'Room number already exists in this building.',
        ];
    }
}
