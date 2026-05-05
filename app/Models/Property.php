<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_APARTMENT = 'apartment';
    public const TYPE_CONDO = 'condo';
    public const TYPE_HOUSE = 'house';

    public const PURPOSE_SALE = 'sale';
    public const PURPOSE_RENT = 'rent';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_SOLD = 'sold';

    protected $fillable = [
        'property_code',
        'property_name',
        'property_type',
        'purpose',
        'owner_user_id',
        'building',
        'floor',
        'unit_number',
        'township',
        'address',
        'bedrooms',
        'bathrooms',
        'area_sqft',
        'status',
        'sale_price',
        'monthly_rent',
        'maintenance_fee',
        'description',
        'featured_image',
        'gallery_images',
        'is_featured',
        'listed_at',
    ];

    protected function casts(): array
    {
        return [
            'gallery_images' => 'array',
            'is_featured' => 'boolean',
            'listed_at' => 'date',
            'sale_price' => 'decimal:2',
            'monthly_rent' => 'decimal:2',
            'maintenance_fee' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'related_property_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function viewingRequests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class);
    }
}
