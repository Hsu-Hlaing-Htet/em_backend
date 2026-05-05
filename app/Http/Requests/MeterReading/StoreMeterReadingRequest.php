<?php

namespace App\Http\Requests\MeterReading;

use App\Models\MeterReading;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterReadingRequest extends FormRequest
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
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'meter_type' => ['required', Rule::in([MeterReading::TYPE_ELECTRICITY, MeterReading::TYPE_WATER])],
            'previous_reading' => ['required', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'gte:previous_reading'],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
        ];
    }
}
