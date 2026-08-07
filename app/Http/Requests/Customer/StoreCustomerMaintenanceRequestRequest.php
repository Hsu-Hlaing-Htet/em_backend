<?php

namespace App\Http\Requests\Customer;

use App\Models\Contract;
use App\Support\MaintenanceRequestOptions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreCustomerMaintenanceRequestRequest extends FormRequest
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
        $roomIds = Contract::query()
            ->where('user_id', $this->user()->id)
            ->whereIn('status', ['approved', 'active'])
            ->pluck('room_id')
            ->unique()
            ->values()
            ->all();

        return [
            'room_id' => ['required', 'integer', Rule::in($roomIds)],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(MaintenanceRequestOptions::CATEGORIES)],
            'priority' => ['required', 'string', Rule::in(MaintenanceRequestOptions::PRIORITIES)],
            'description' => ['required', 'string', 'max:5000'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'resolution_note' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.in' => 'Selected room is not linked to an active approved contract.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'data' => $validator->errors(),
        ], 422));
    }
}
