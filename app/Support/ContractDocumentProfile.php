<?php

namespace App\Support;

use App\Mail\RentContractDocumentMail;
use App\Mail\SaleContractDocumentMail;

final class ContractDocumentProfile
{
    /**
     * @param  class-string  $mailClass
     */
    public function __construct(
        public readonly string $type,
        public readonly string $view,
        public readonly string $defaultFilename,
        public readonly string $priceLabel,
        public readonly string $depositLabel,
        public readonly string $mailSubjectPrefix,
        public readonly string $tenantSignatureLabel,
        public readonly string $mailClass,
    ) {}

    public static function sale(): self
    {
        return new self(
            type: 'sale',
            view: 'sale-contracts.document',
            defaultFilename: 'sale-contract',
            priceLabel: 'Sale Price',
            depositLabel: 'Booking Deposit',
            mailSubjectPrefix: 'Property Sale Agreement',
            tenantSignatureLabel: 'Customer Signature',
            mailClass: SaleContractDocumentMail::class,
        );
    }

    public static function rent(): self
    {
        return new self(
            type: 'rent',
            view: 'rent-contracts.document',
            defaultFilename: 'rent-contract',
            priceLabel: 'Rent Price',
            depositLabel: 'Security Deposit',
            mailSubjectPrefix: 'Property Rent Agreement',
            tenantSignatureLabel: 'Tenant Signature',
            mailClass: RentContractDocumentMail::class,
        );
    }

    public static function fromType(string $type): self
    {
        return match ($type) {
            'rent' => self::rent(),
            default => self::sale(),
        };
    }
}
