<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OWNER = 'owner';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'owner_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by_user_id');
    }

    public function recordedMeterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'recorded_by_user_id');
    }

    public function approvedViewingRequests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class, 'approved_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
