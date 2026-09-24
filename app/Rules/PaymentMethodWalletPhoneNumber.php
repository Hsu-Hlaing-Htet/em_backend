<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Payment-method wallet phone: required Myanmar mobile in local or +95 form.
 */
class PaymentMethodWalletPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(PhoneNumber::REQUIRED_MESSAGE);

            return;
        }

        if (! PhoneNumber::isValidPaymentMethodWalletPhone($value)) {
            $fail(PhoneNumber::INVALID_MESSAGE);
        }
    }
}
