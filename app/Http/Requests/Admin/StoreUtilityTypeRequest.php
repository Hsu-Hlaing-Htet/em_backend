<?php

namespace App\Http\Requests\Admin;

use App\Models\UtilityType;
use Illuminate\Validation\Rule;

class StoreUtilityTypeRequest extends BaseAdminFormRequest
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('utility_types', 'slug')],
            'status' => ['required', 'string', Rule::in(UtilityType::statuses())],
        ];
    }
}
