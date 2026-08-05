<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExportListPdfRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'filename' => ['required', 'string', 'max:255'],
            'landscape' => ['sometimes', 'boolean'],
            'generated_by' => ['nullable', 'string', 'max:255'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.field' => ['required', 'string', 'max:100'],
            'columns.*.header' => ['required', 'string', 'max:255'],
            'rows' => ['required', 'array'],
            'rows.*' => ['array'],
            'filters' => ['sometimes', 'array'],
            'filters.*.label' => ['required_with:filters', 'string', 'max:255'],
            'filters.*.value' => ['nullable', 'string', 'max:500'],
        ];
    }
}
