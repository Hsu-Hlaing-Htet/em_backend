<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesUserEmail;
use App\Http\Requests\Concerns\ValidatesPhoneNumber;
use App\Support\UserEmail;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends BaseAdminFormRequest
{
    use ValidatesPhoneNumber;
    use ValidatesUserEmail;

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
            'email' => UserEmail::createRules(),
            'password' => ['prohibited'],
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
        return [
            ...$this->userEmailMessages(),
            ...$this->phoneMessages(),
            'username.unique' => 'This username is already in use.',
        ];
    }
}
