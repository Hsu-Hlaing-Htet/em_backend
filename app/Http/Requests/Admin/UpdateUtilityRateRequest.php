<?php

namespace App\Http\Requests\Admin;

use App\Models\UtilityRate;
use Illuminate\Validation\Rule;

class UpdateUtilityRateRequest extends BaseAdminFormRequest
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
            'utility_type_id' => ['required', 'integer', Rule::exists('utility_types', 'id')],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(UtilityRate::statuses())],
        ];
    }
}
