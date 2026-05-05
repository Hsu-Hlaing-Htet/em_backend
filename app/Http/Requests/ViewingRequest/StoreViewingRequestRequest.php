<?php

namespace App\Http\Requests\ViewingRequest;

use App\Models\ViewingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreViewingRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'requester_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'request_type' => ['required', Rule::in([
                ViewingRequest::TYPE_VIEWING,
                ViewingRequest::TYPE_BOOKING,
            ])],
        ];
    }
}
