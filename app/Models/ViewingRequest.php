<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewingRequest extends Model
{
    use HasFactory;

    public const TYPE_VIEWING = 'viewing';
    public const TYPE_BOOKING = 'booking';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RESERVED = 'reserved';

    protected $fillable = [
        'property_id',
        'requester_name',
        'email',
        'phone',
        'message',
        'preferred_date',
        'request_type',
        'status',
        'approved_by_user_id',
        'reservation_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'reservation_expires_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
