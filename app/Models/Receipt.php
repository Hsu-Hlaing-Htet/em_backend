<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'payment_id',
        'receipt_number',
        'receipt_pdf_path',
        'status',
        'approval_status',
        'issued_at',
        'sent_at',
        'sent_by',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function canBeIssued(): bool
    {
        return $this->isApproved() && $this->status === self::STATUS_DRAFT && $this->sent_at === null;
    }

    public function canBeEmailed(): bool
    {
        return $this->isApproved()
            && $this->status === self::STATUS_DRAFT
            && $this->sent_at === null;
    }

    public function isDeliveredToCustomer(): bool
    {
        return $this->status === self::STATUS_ISSUED && $this->sent_at !== null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Receipt>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Receipt>
     */
    public function scopeDeliveredToCustomer($query)
    {
        return $query
            ->where('status', self::STATUS_ISSUED)
            ->whereNotNull('sent_at');
    }
}
