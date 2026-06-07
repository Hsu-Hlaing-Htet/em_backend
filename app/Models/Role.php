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
}
