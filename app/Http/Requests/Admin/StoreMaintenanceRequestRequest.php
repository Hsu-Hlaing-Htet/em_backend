<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreMaintenanceRequestRequest extends BaseAdminFormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }
}
