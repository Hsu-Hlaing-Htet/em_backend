<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends BaseAdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            // Amount applied to the invoice (settled charge), not cash tender.
            'amount' => ['required', 'numeric', 'gt:0'],
            // Cash tendered; required for cash methods, ignored for wallet/bank.
            'amount_received' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            // payment_date is set by PaymentService from the server clock — not client-controlled.
            'payment_date' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'approved_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $methodId = (int) $this->input('payment_method_id');
            $method = PaymentMethod::query()->find($methodId);

            if (! $method) {
                return;
            }

            if ($method->status !== PaymentMethod::STATUS_ACTIVE) {
                $validator->errors()->add('payment_method_id', 'Selected payment method is inactive.');

                return;
            }

            $applied = round((float) $this->input('amount'), 2);
            $received = $this->input('amount_received');

            if ($method->type === PaymentMethod::TYPE_CASH) {
                if ($received === null || $received === '') {
                    $validator->errors()->add('amount_received', 'Received amount is required for cash payments.');

                    return;
                }

                $receivedAmount = round((float) $received, 2);

                if ($receivedAmount < $applied) {
                    $validator->errors()->add(
                        'amount_received',
                        'Received amount cannot be less than the amount applied to the invoice.',
                    );
                }

                return;
            }

            // Non-cash: do not accept a separate cash-tender amount.
            if ($received !== null && $received !== '' && abs(round((float) $received, 2) - $applied) > 0.009) {
                $validator->errors()->add(
                    'amount_received',
                    'Received amount is only used for cash payments.',
                );
            }
        });
    }
}
