<?php

namespace App\Http\Requests\Admin;

class UpdateReceiptRequest extends BaseAdminFormRequest
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
            'receipt_pdf_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
