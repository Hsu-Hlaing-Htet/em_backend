<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LateFee extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    public const PER_DAY = 'day';

    public const PER_MONTH = 'month';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'value',
        'per',
        'grace_days',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'grace_days' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_FIXED,
            self::TYPE_PERCENTAGE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function perOptions(): array
    {
        return [
            self::PER_DAY,
            self::PER_MONTH,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }
}
