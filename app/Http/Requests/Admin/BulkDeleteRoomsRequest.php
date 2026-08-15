<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class BulkDeleteRoomsRequest extends BaseAdminFormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('rooms', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
