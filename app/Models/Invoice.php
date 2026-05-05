<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'invoice_number',
        'property_id',
        'contract_id',
        'user_id',
        'tenant_id',
        'customer_name',
        'issued_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'status',
        'sent_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function outstandingBalance(): float
    {
        return max((float) $this->total_amount - (float) $this->paid_amount, 0.0);
    }
}
