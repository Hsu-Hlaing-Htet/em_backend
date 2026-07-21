<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'payment_type', 'duration_months', 'interest_percentage', 'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'interest_percentage' => 'decimal:2',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
