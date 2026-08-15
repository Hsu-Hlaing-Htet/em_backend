<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'building_name',
        'location',
        'description',
        'status',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
