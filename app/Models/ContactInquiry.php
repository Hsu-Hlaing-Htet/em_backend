<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const PREFERRED_SERVICES = [
        'General Enquiry',
        'Property Viewing',
        'Buying Support',
        'Rental Support',
        'Investment Advisory',
        'Property Management',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'preferred_service',
        'message',
        'status',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_READ,
        ];
    }

    public function markAsRead(): self
    {
        if ($this->status === self::STATUS_READ) {
            return $this;
        }

        $this->forceFill([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ])->save();

        return $this->fresh();
    }
}
