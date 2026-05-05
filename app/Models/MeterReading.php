<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    use HasFactory;

    public const TYPE_ELECTRICITY = 'electricity';
    public const TYPE_WATER = 'water';

    protected $fillable = [
        'property_id',
        'contract_id',
        'meter_type',
        'previous_reading',
        'current_reading',
        'usage',
        'rate_per_unit',
        'calculated_amount',
        'reading_date',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'usage' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'calculated_amount' => 'decimal:2',
            'reading_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
