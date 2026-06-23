<?php

namespace App\Http\Requests\Admin;

use App\Models\ChargeType;
use Illuminate\Validation\Rule;

class StoreChargeTypeRequest extends BaseAdminFormRequest
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
            'status' => ['required', 'string', Rule::in(ChargeType::statuses())],
        ];
    }
}
