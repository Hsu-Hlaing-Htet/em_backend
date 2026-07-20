<?php

namespace App\Support;

final class ContractDraftProfile
{
    /**
     * @param  list<string>  $roomTypes
     */
    public function __construct(
        public readonly string $type,
        public readonly string $numberPrefix,
        public readonly string $activeStatus,
        public readonly string $roomStatusOnApprove,
        public readonly array $roomTypes,
        public readonly string $priceColumn,
        public readonly string $depositColumn,
        public readonly string $unavailableRoomMessage,
        public readonly string $draftOnlyMessage,
    ) {}

    public static function sale(): self
    {
        return new self(
            type: 'sale',
            numberPrefix: 'S-',
            activeStatus: 'approved',
            roomStatusOnApprove: 'reserved',
            roomTypes: ['sale', 'both'],
            priceColumn: 'sale_price',
            depositColumn: 'booking_deposit_price',
            unavailableRoomMessage: 'Selected room is not available for sale.',
            draftOnlyMessage: 'Only sale contract drafts can be modified.',
        );
    }

    public static function rent(): self
    {
        return new self(
            type: 'rent',
            numberPrefix: 'R-',
            activeStatus: 'active',
            roomStatusOnApprove: 'occupied',
            roomTypes: ['rent', 'both'],
            priceColumn: 'rent_price',
            depositColumn: 'rent_deposit_price',
            unavailableRoomMessage: 'Selected room is not available for rent.',
            draftOnlyMessage: 'Only rent contract drafts can be modified.',
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
