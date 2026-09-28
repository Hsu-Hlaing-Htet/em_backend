<?php

namespace App\Support;

final class DocumentFilename
{
    public static function pdf(?string $documentNumber, string $fallback): string
    {
        return self::sanitizeSegment($documentNumber, $fallback).'.pdf';
    }

    public static function utility(int $id): string
    {
        return sprintf('UTL-%06d.pdf', $id);
    }

    public static function sanitizeSegment(?string $value, string $fallback = 'UNKNOWN'): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $value) ?? '';
        $sanitized = trim($sanitized, '-');

        return $sanitized !== '' ? $sanitized : $fallback;
    }
}
