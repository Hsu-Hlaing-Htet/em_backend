<?php

namespace App\Http\Requests\Admin;

use App\Models\MaintenanceCategory;
use Illuminate\Validation\Rule;

class StoreMaintenanceCategoryRequest extends BaseAdminFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(MaintenanceCategory::statuses())],
        ];
    }
}
