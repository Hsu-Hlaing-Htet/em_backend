<?php

namespace App\Http\Requests\Admin;

use App\Support\MaintenanceRequestOptions;
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
            'category' => ['sometimes', 'string', Rule::in(MaintenanceRequestOptions::CATEGORIES)],
            'priority' => ['sometimes', 'string', Rule::in(MaintenanceRequestOptions::PRIORITIES)],
            'description' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'resolution_note' => ['prohibited'],
        ];
    }
}
