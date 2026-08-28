<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseAdminFormRequest
{
    use ValidatesPhoneNumber;

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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('profiles', 'user_id')->ignore($this->route('profile')),
            ],
            'phone' => $this->phoneRules(),
            'nrc' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->phoneMessages();
    }
}
