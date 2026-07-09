<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UploadPaymentProofRequest extends BaseAdminFormRequest
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
            'proof' => ['required', 'file', 'image', 'max:5120'],
        ];
    }
}
