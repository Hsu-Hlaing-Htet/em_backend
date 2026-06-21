<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_SALE = 'sale';

    public const TYPE_RENT = 'rent';

    public const TYPE_BOTH = 'both';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_SOLD = 'sold';

    public const STATUS_MAINTENANCE = 'maintenance';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'building_id',
        'room_number',
        'floor_number',
        'area_sqft',
        'description',
        'type',
        'status',
        'sale_price',
        'rent_price',
        'rent_deposit_price',
        'booking_deposit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor_number' => 'integer',
            'area_sqft' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'rent_price' => 'decimal:2',
            'rent_deposit_price' => 'decimal:2',
            'booking_deposit_price' => 'decimal:2',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function roomImages(): HasMany
    {
        return $this->hasMany(RoomImage::class);
    }

    public function primaryRoomImage(): HasOne
    {
        return $this->hasOne(RoomImage::class)->where('is_primary', true);
    }
}
