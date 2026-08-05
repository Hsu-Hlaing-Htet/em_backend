<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

final class DocumentFilename
{
    public static function utility(?DateTimeInterface $billingMonth, ?string $roomNumber): string
    {
        return self::build('UTL', $billingMonth, $roomNumber);
    }

    public static function invoice(?DateTimeInterface $issuedDate, ?string $roomNumber, ?string $invoiceNumber): string
    {
        return self::build('INV', $issuedDate, $roomNumber, $invoiceNumber);
    }

    public static function receipt(?DateTimeInterface $issuedAt, ?string $roomNumber, ?string $receiptNumber): string
    {
        return self::build('RCP', $issuedAt, $roomNumber, $receiptNumber);
    }

    public static function rentContract(?DateTimeInterface $startDate, ?string $roomNumber, ?string $contractNumber): string
    {
        return self::build('R', $startDate, $roomNumber, $contractNumber);
    }

    public static function saleContract(?DateTimeInterface $startDate, ?string $roomNumber, ?string $contractNumber): string
    {
        return self::build('S', $startDate, $roomNumber, $contractNumber);
    }

    public static function build(
        string $type,
        ?DateTimeInterface $date,
        ?string $roomNumber,
        ?string $documentNumber = null,
    ): string {
        $parts = [
            self::sanitizeSegment($type, 'DOC'),
            self::year($date),
            self::month($date),
            self::sanitizeSegment($roomNumber, 'UNKNOWN'),
        ];

        if ($documentNumber !== null && $documentNumber !== '') {
            $parts[] = self::numericPart($documentNumber);
        }

        return implode('-', $parts).'.pdf';
    }

    public static function numericPart(string $documentNumber): string
    {
        if (preg_match('/(\d+)\s*$/', $documentNumber, $matches) === 1) {
            return $matches[1];
        }

        return '000000';
    }

    public static function sanitizeSegment(?string $value, string $fallback = 'UNKNOWN'): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $value) ?? '';
        $sanitized = trim($sanitized, '-');

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    private static function year(?DateTimeInterface $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y');
        }

        return $date?->format('Y') ?? '0000';
    }

    private static function month(?DateTimeInterface $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('m');
        }

        return $date?->format('m') ?? '00';
    }
}
