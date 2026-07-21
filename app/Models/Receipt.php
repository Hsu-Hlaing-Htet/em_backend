<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id', 'receipt_number', 'receipt_pdf_path', 'status', 'issued_at', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
