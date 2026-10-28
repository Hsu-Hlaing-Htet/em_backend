<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const CUSTOMER = 'customer';

    public const MANAGER = 'manager';

    public const ACCOUNTANT = 'accountant';

    public const RECEPTIONIST = 'receptionist';

    public const SECURITY_GUARD = 'security_guard';

    public const CLEANER = 'cleaner';

    public const MAINTENANCE_STAFF = 'maintenance_staff';

    public const ELECTRICIAN = 'electrician';

    public const PLUMBER = 'plumber';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function findByName(string $name): ?self
    {
        return static::query()->where('name', $name)->first();
    }

    /**
     * @return list<string>
     */
    public static function staffRoleNames(): array
    {
        return [
            self::MANAGER,
            self::ACCOUNTANT,
            self::RECEPTIONIST,
            self::SECURITY_GUARD,
            self::CLEANER,
            self::MAINTENANCE_STAFF,
            self::ELECTRICIAN,
            self::PLUMBER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function loginRoleNames(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN, self::CUSTOMER];
    }
}
