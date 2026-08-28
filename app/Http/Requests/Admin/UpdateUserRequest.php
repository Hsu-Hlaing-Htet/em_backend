<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesUserEmail;
use App\Support\UserEmail;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseAdminFormRequest
{
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
        $userId = $this->userIdForEmailValidation();

        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => UserEmail::updateRules(
                (int) $userId,
                $this->currentEmailForValidation(),
            ),
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->userEmailMessages(),
            'username.unique' => 'This username is already in use.',
        ];
    }
}
