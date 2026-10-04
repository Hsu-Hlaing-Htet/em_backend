<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'created_by',
        'approved_by',
        'approved_at',
        'title',
        'maintenance_category_id',
        'category',
        'priority',
        'description',
        'attachment_path',
        'status',
        'rejection_reason',
        'resolution_note',
        'maintenance_fee_amount',
        'charge_description',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'maintenance_fee_amount' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function maintenanceCategory(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCategory::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
