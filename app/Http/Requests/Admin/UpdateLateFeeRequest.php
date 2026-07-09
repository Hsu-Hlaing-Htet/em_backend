<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateLateFeeRequest extends BaseAdminFormRequest
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
            'type' => ['required', 'string', Rule::in(['fixed', 'percentage'])],
            'value' => ['required', 'numeric', 'min:0'],
            'per' => ['required', 'string', Rule::in(['day', 'month'])],
            'grace_days' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'approved_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'approved_at' => ['nullable', 'date'],
        ];
    }
}
