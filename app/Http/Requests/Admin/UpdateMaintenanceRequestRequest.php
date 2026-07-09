<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateMaintenanceRequestRequest extends BaseAdminFormRequest
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
            'user_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
