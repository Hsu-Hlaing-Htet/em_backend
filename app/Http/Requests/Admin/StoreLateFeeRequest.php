<?php

namespace App\Http\Requests\Admin;

use App\Models\LateFee;
use Illuminate\Validation\Rule;

class StoreLateFeeRequest extends BaseAdminFormRequest
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
            'type' => ['required', 'string', Rule::in(LateFee::types())],
            'value' => ['required', 'numeric', 'min:0'],
            'per' => ['required', 'string', Rule::in(LateFee::perOptions())],
            'grace_days' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(LateFee::statuses())],
        ];
    }
}
