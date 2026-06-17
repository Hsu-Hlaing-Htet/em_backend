<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreProfileRequest extends BaseAdminFormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::unique('profiles', 'user_id')],
            'phone' => ['required', 'string', 'max:50'],
            'nrc' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
