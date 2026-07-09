<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

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
            'image' => ['required', 'image', 'max:5120'],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
        ];
    }
}
