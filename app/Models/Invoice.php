<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id', 'utility_id', 'created_by', 'approved_by', 'approved_at', 'invoice_number', 'type',
        'issued_date', 'due_date', 'billing_month', 'late_fee', 'total_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'due_date' => 'date',
            'billing_month' => 'date',
            'late_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function utility(): BelongsTo { return $this->belongsTo(Utility::class); }
    public function utilities(): HasMany { return $this->hasMany(Utility::class); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
