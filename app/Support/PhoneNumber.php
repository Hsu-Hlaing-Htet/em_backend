<?php

namespace App\Support;

/**
 * Myanmar mobile helpers.
 *
 * Profile / staff / resident phones use E.164: +95 + 9 local digits (+959xxxxxxxx).
 * Payment-method wallet phones use local: 09 + 9 digits (09xxxxxxxxx).
 */
final class PhoneNumber
{
    public const COUNTRY_CODE = '+95';

    public const LOCAL_DIGIT_LENGTH = 9;

    public const REQUIRED_MESSAGE = 'Phone number is required.';

    public const INVALID_MESSAGE = 'Please enter a valid phone number.';

    /**
     * Exact E.164 profile phone: +95 followed by exactly 9 digits.
     */
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^\\+95\\d{9}$/', $value) === 1;
    }

    /**
     * Normalize payment-method wallet phones to local 09xxxxxxxxx.
     *
     * Accepts (with optional spaces/punctuation):
     * - 09779959901
     * - 09 779 959 901
     * - +959779959901
     * - +95 9779959901
     * - 9779959901
     */
    public static function normalizePaymentMethodWalletPhone(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        // Local 09xxxxxxxxx (11 digits).
        if (preg_match('/^09\\d{9}$/', $digits) === 1) {
            return $digits;
        }

        // National mobile without trunk 0: 9xxxxxxxxx (10 digits).
        if (preg_match('/^9\\d{9}$/', $digits) === 1) {
            return '0'.$digits;
        }

        // Country code 95 + national 9xxxxxxxxx (12 digits).
        if (preg_match('/^959\\d{9}$/', $digits) === 1) {
            return '0'.substr($digits, 2);
        }

        return null;
    }

    public static function isValidPaymentMethodWalletPhone(mixed $value): bool
    {
        return self::normalizePaymentMethodWalletPhone($value) !== null;
    }
}
