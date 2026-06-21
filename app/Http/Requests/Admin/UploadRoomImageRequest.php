<?php

namespace App\Http\Requests\Admin;

class UploadRoomImageRequest extends BaseAdminFormRequest
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
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'image' => ['required', 'image', 'max:5120'],
        ];
    }
}
