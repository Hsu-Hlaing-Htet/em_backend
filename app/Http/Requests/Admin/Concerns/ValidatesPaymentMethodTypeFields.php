<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\PaymentMethod;
use App\Rules\PaymentMethodWalletPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

trait ValidatesPaymentMethodTypeFields
{
    /**
     * Store: always enforce type-specific required fields.
     * Update: enforce when the field is present, or when switching into that type.
     * Status-only toggles omit type-specific keys and must succeed without re-sending them.
     *
     * @return array<string, mixed>
     */
    protected function paymentMethodTypeFieldRules(): array
    {
        return [
            'account_name' => [
                Rule::requiredIf(fn () => $this->requiresBankAccountField('account_name')),
                'nullable',
                'string',
                'max:255',
            ],
            'account_number' => [
                Rule::requiredIf(fn () => $this->requiresBankAccountField('account_number')),
                'nullable',
                'string',
                'max:100',
            ],
            'phone_number' => $this->walletPhoneRules(),
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function walletPhoneRules(): array
    {
        if (! $this->requiresWalletPhoneField()) {
            return ['nullable', 'string', 'max:50'];
        }

        return ['required', 'string', 'max:50', new PaymentMethodWalletPhoneNumber];
    }

    protected function requiresWalletPhoneField(): bool
    {
        if ($this->resolvedPaymentMethodType() !== PaymentMethod::TYPE_WALLET) {
            return false;
        }

        if ($this->isCreatingPaymentMethod()) {
            return true;
        }

        if ($this->exists('phone_number')) {
            return true;
        }

        return $this->isSwitchingToType(PaymentMethod::TYPE_WALLET);
    }

    protected function requiresBankAccountField(string $field): bool
    {
        if ($this->resolvedPaymentMethodType() !== PaymentMethod::TYPE_BANK_TRANSFER) {
            return false;
        }

        if ($this->isCreatingPaymentMethod()) {
            return true;
        }

        if ($this->exists($field)) {
            return true;
        }

        return $this->isSwitchingToType(PaymentMethod::TYPE_BANK_TRANSFER);
    }

    protected function isCreatingPaymentMethod(): bool
    {
        return $this->route('payment_method') === null;
    }

    protected function isSwitchingToType(string $type): bool
    {
        if (! $this->filled('type')) {
            return false;
        }

        /** @var PaymentMethod|null $existing */
        $existing = $this->route('payment_method');

        return $existing !== null
            && (string) $existing->type !== $type
            && (string) $this->input('type') === $type;
    }

    protected function resolvedPaymentMethodType(): string
    {
        $type = $this->input('type');

        if (is_string($type) && $type !== '') {
            return $type;
        }

        /** @var PaymentMethod|null $method */
        $method = $this->route('payment_method');

        return (string) ($method?->type ?? '');
    }

    /**
     * Normalize wallet phones before validation so FE/BE share one format contract.
     */
    protected function normalizePaymentMethodWalletPhoneInput(): void
    {
        if (! $this->exists('phone_number')) {
            return;
        }

        $raw = $this->input('phone_number');

        if (! is_string($raw) && ! is_numeric($raw)) {
            return;
        }

        if (trim((string) $raw) === '') {
            $this->merge(['phone_number' => '']);

            return;
        }

        $normalized = PhoneNumber::normalizePaymentMethodWalletPhone($raw);

        if ($normalized !== null) {
            $this->merge(['phone_number' => $normalized]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function paymentMethodTypeFieldMessages(): array
    {
        return [
            'phone_number.required' => PhoneNumber::REQUIRED_MESSAGE,
            'account_name.required' => 'The account name field is required.',
            'account_number.required' => 'The account number field is required.',
        ];
    }
}
