<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreReceiptRequest extends BaseAdminFormRequest
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
            'payment_id' => ['required', 'integer', Rule::exists('payments', 'id')],
            'receipt_pdf_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
