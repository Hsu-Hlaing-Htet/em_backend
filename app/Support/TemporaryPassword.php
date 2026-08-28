<?php

namespace App\Support;

final class TemporaryPassword
{
    public const VALUE = 'p@ssword';

    /**
     * Fixed temporary password for newly created resident and staff accounts.
     */
    public static function generate(): string
    {
        return self::VALUE;
    }
}
