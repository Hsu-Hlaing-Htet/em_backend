<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'utility_id', 'utility_type_id', 'previous_reading', 'current_reading', 'usage', 'unit_price', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'usage' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function utility(): BelongsTo { return $this->belongsTo(Utility::class); }
    public function utilityType(): BelongsTo { return $this->belongsTo(UtilityType::class); }
}
