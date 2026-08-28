<?php

namespace Database\Seeders\Support;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Receipt;

/**
 * Shared sequential codes for seeders and factories only.
 *
 * Formats:
 * - Rooms: A-101, B-101, C-101, D-101, A-102, …
 * - Sale contracts: S-000001
 * - Rent contracts: R-000001
 * - Invoices: INV-000001
 * - Receipts: RCP-000001
 */
final class SeedNumberGenerator
{
    private static int $saleSequence = 0;

    private static int $rentSequence = 0;

    private static int $invoiceSequence = 0;

    private static int $receiptSequence = 0;

    private static int $roomIndex = 0;

    public static function reset(): void
    {
        self::$saleSequence = self::maxNumericSuffix(Contract::class, 'contract_number', 'S-');
        self::$rentSequence = self::maxNumericSuffix(Contract::class, 'contract_number', 'R-');
        self::$invoiceSequence = self::maxNumericSuffix(Invoice::class, 'invoice_number', 'INV-');
        self::$receiptSequence = self::maxNumericSuffix(Receipt::class, 'receipt_number', 'RCP-');
        self::$roomIndex = 0;
    }

    public static function nextSaleContractNumber(): string
    {
        self::$saleSequence++;

        return self::formatPrefixed(self::$saleSequence, 'S-');
    }

    public static function nextRentContractNumber(): string
    {
        self::$rentSequence++;

        return self::formatPrefixed(self::$rentSequence, 'R-');
    }

    public static function nextInvoiceNumber(): string
    {
        self::$invoiceSequence++;

        return self::formatPrefixed(self::$invoiceSequence, 'INV-');
    }

    public static function nextReceiptNumber(): string
    {
        self::$receiptSequence++;

        return self::formatPrefixed(self::$receiptSequence, 'RCP-');
    }

    /**
     * Factory helper: A-101, B-101, C-101, D-101, A-102, B-102, …
     */
    public static function nextRoomNumber(): string
    {
        $index = self::$roomIndex++;
        $letter = chr(65 + ($index % 4));
        $unit = 101 + intdiv($index, 4);

        return sprintf('%s-%d', $letter, $unit);
    }

    /**
     * Bulk seeding helper when rooms are distributed round-robin across buildings.
     * Example with 3 buildings: A-101, B-101, C-101, A-102, B-102, C-102.
     */
    public static function roomNumberForIndex(int $index, int $buildingIndex, int $buildingCount): string
    {
        $buildingCount = max(1, $buildingCount);
        $letter = chr(65 + (abs($buildingIndex) % 26));
        $unit = 101 + intdiv(max(0, $index), $buildingCount);

        return sprintf('%s-%d', $letter, $unit);
    }

    public static function formatPrefixed(int $sequence, string $prefix): string
    {
        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function maxNumericSuffix(string $modelClass, string $column, string $prefix): int
    {
        return $modelClass::query()
            ->where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(function (string $number) use ($prefix): int {
                if (! preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $number, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;
    }
}
