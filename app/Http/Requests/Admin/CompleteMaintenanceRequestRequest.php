<?php

namespace App\Http\Requests\Admin;

class CompleteMaintenanceRequestRequest extends BaseAdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('maintenance_fee') && ! $this->filled('maintenance_fee_amount')) {
            $this->merge([
                'maintenance_fee_amount' => $this->input('maintenance_fee'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'maintenance_fee' => ['nullable', 'numeric', 'min:0'],
            'maintenance_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'charge_description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->resolvedMaintenanceFee() <= 0) {
                return;
            }

            $description = $this->input('charge_description');

            if (! is_string($description) || trim($description) === '') {
                $validator->errors()->add(
                    'charge_description',
                    'Charge description is required when a maintenance fee is charged.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'charge_description.required' => 'Charge description is required when a maintenance fee is charged.',
        ];
    }

    public function resolvedMaintenanceFee(): float
    {
        $amount = $this->input('maintenance_fee_amount', $this->input('maintenance_fee'));

        if ($amount === null || $amount === '') {
            return 0.0;
        }

        return round((float) $amount, 2);
    }
}
