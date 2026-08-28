<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExportDocumentPreviewPdfRequest extends FormRequest
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
            'html' => ['required', 'string', 'max:1000000'],
            'filename' => ['required', 'string', 'max:255'],
        ];
    }
}
