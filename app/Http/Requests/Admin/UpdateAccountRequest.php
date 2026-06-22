<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateAccountRequest extends BaseAdminFormRequest
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
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:50'],
            'nrc' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
