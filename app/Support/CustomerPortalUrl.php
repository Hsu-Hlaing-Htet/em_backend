<?php

namespace App\Support;

use App\Models\User;

final class CustomerPortalUrl
{
    public static function base(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    public static function login(): string
    {
        return self::base().'/login';
    }

    public static function invoice(int $invoiceId): string
    {
        return self::base().'/customer/invoices/'.$invoiceId;
    }

    public static function invoiceDocument(int $invoiceId): string
    {
        return self::base().'/customer/invoices/'.$invoiceId.'/document';
    }

    public static function receipt(int $receiptId): string
    {
        return self::base().'/customer/receipts/'.$receiptId;
    }

    public static function contract(int $contractId): string
    {
        return self::base().'/customer/contracts/'.$contractId;
    }

    public static function invoicesList(): string
    {
        return self::base().'/customer/invoices';
    }

    public static function payment(int $paymentId): string
    {
        return self::base().'/customer/payments/'.$paymentId;
    }

    /**
     * Resolve display name for a party email on a contract.
     *
     * @param  iterable<int, User>  $partyUsers
     */
    public static function customerNameForEmail(iterable $partyUsers, string $email, ?string $fallback = null): string
    {
        foreach ($partyUsers as $user) {
            if (! $user?->email) {
                continue;
            }

            if (strcasecmp(trim((string) $user->email), trim($email)) === 0) {
                return trim((string) ($user->name ?? '')) ?: ($fallback ?: 'Customer');
            }
        }

        return $fallback ?: 'Customer';
    }
}
