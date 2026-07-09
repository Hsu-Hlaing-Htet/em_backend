<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

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

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function roomImages(): HasMany
    {
        return $this->hasMany(RoomImage::class);
    }
}
