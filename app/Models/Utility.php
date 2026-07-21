<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Utility extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'billing_month', 'total_amount', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function items(): HasMany { return $this->hasMany(UtilityItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
