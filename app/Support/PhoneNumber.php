<?php

namespace App\Support;

final class PhoneNumber
{
    public const COUNTRY_CODE = '+95';
    public const LOCAL_DIGIT_LENGTH = 9;
    public const REQUIRED_MESSAGE = 'Phone number is required.';
    public const INVALID_MESSAGE = 'Please enter a valid phone number.';

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match('/^\\+95\\d{9}$/', $value) === 1;
    }
}
