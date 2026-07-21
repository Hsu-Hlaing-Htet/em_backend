<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id', 'user_id', 'created_by', 'approved_by', 'approved_at', 'title', 'description', 'status',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
