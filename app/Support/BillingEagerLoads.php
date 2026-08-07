<?php

namespace App\Support;

/**
 * Canonical eager-load graphs for billing API resources.
 * Keep list/detail/mutation responses consistent so Vue never sees omitted fields.
 */
final class BillingEagerLoads
{
    /**
     * @return list<string>
     */
    public static function payment(): array
    {
        return [
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'invoice.items.chargeType',
            'invoice.utility.items.utilityType',
            'invoice.utilities.items.utilityType',
            'invoice.payments.paymentMethod',
            'paymentMethod',
            'receipt',
            'creator',
            'approver',
        ];
    }

    /**
     * @return list<string>
     */
    public static function paymentList(): array
    {
        return [
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'invoice.items.chargeType',
            'invoice.utility.items.utilityType',
            'invoice.utilities.items.utilityType',
            'invoice.payments',
            'paymentMethod',
            'receipt',
        ];
    }

    /**
     * @return list<string>
     */
    public static function invoice(): array
    {
        return [
            'contract.user.profile',
            'contract.room.building',
            'items.chargeType',
            'utility.items.utilityType',
            'utilities.items.utilityType',
            'payments.paymentMethod',
            'creator',
            'approver',
        ];
    }

    /**
     * @return list<string>
     */
    public static function invoiceList(): array
    {
        return [
            'contract.user.profile',
            'contract.room.building',
            'items.chargeType',
            'utility.items.utilityType',
            'utilities.items.utilityType',
            'payments.paymentMethod',
            'creator',
            'approver',
        ];
    }

    /**
     * @return list<string>
     */
    public static function receipt(): array
    {
        return [
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.invoice.items.chargeType',
            'payment.invoice.utility.items.utilityType',
            'payment.invoice.utilities.items.utilityType',
            'payment.invoice.payments',
            'payment.paymentMethod',
            'creator',
            'approver',
            'sender',
        ];
    }

    /**
     * @return list<string>
     */
    public static function utility(): array
    {
        return [
            'room.building',
            'items.utilityType',
            'creator',
            'approver',
        ];
    }
}
