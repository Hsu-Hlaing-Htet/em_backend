<?php

namespace App\Http\Requests\Admin;

use App\Models\UtilityType;
use Illuminate\Validation\Rule;

class PreviewUtilityBulkImportRequest extends BaseAdminFormRequest
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
            'utility_type_id' => [
                'required',
                'integer',
                Rule::exists('utility_types', 'id')->where('status', UtilityType::STATUS_ACTIVE),
            ],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.building' => ['nullable', 'string'],
            'rows.*.room_number' => ['nullable', 'string'],
            'rows.*.billing_month' => ['nullable'],
            'rows.*.reading_date' => ['nullable'],
            'rows.*.current_reading' => ['nullable'],
        ];
    }
}
