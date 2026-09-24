<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_WALLET = 'wallet';

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_CHEQUE = 'cheque';

    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'account_name',
        'account_number',
        'phone_number',
        'qr_image_path',
        'instructions',
        'status',
        'is_customer_visible',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
            'sort_order' => 'integer',
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

    /**
     * Controlled payment method types for Admin configuration.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_WALLET,
            self::TYPE_CASH,
            self::TYPE_BANK_TRANSFER,
            self::TYPE_CHEQUE,
            self::TYPE_OTHER,
        ];
    }

    public function isWallet(): bool
    {
        return $this->type === self::TYPE_WALLET;
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    public function isAvailableForCustomer(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->type !== self::TYPE_CASH;
    }

    public function qrImageUrl(): ?string
    {
        if (! $this->qr_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_image_path);
    }

    /**
     * Customer Portal availability: Active and not Cash.
     * Cash may remain Active for Admin/office use but never for customers.
     *
     * @param  Builder<PaymentMethod>  $query
     * @return Builder<PaymentMethod>
     */
    public function scopeAvailableForCustomer(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('type', '!=', self::TYPE_CASH);
    }

    /**
     * Keep legacy is_customer_visible column aligned with Active + non-Cash.
     */
    public static function syncCustomerVisibleFlag(?string $type, ?string $status): bool
    {
        return $status === self::STATUS_ACTIVE
            && $type !== null
            && $type !== self::TYPE_CASH;
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
