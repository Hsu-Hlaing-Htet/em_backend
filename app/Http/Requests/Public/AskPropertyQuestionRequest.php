<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskPropertyQuestionRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:1', 'max:2000'],
            'property_id' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', Rule::in(['rent', 'sale'])],
        ];
    }
}
