<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\Concerns\ValidatesUserEmail;
use App\Support\UserEmail;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProfileRequest extends FormRequest
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
        $userId = (int) $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => UserEmail::updateRules(
                $userId,
                $this->user()?->email,
            ),
            'phone' => ['required', 'string', 'max:50'],
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
            'username.unique' => 'This username is already in use.',
        ];
    }
}
