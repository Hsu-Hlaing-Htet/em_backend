<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'contract_number', 'user_id', 'second_user_id', 'room_id', 'payment_plan_id', 'created_by', 'approved_by', 'approved_at',
        'contract_total', 'deposit_amount', 'type', 'payment_type', 'duration_months', 'start_date', 'end_date',
        'billing_day', 'status', 'termination_date', 'termination_reason', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'contract_total' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'termination_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function secondUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    /**
     * Contracts where the user is Customer 1 or Customer 2.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Contract>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Contract>
     */
    public function scopeAccessibleBy($query, User|int $user)
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return $query->where(function ($builder) use ($userId): void {
            $builder->where('user_id', $userId)
                ->orWhere('second_user_id', $userId);
        });
    }

    public function isAccessibleBy(User|int $user): bool
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        return (int) $this->user_id === $userId
            || ($this->second_user_id !== null && (int) $this->second_user_id === $userId);
    }

    /**
     * Unique linked party user IDs (Customer 1 and optional Customer 2).
     *
     * @return list<int>
     */
    public function partyUserIds(): array
    {
        $ids = [(int) $this->user_id];

        if ($this->second_user_id) {
            $ids[] = (int) $this->second_user_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Linked contract parties (Customer 1 and optional Customer 2), unique by id.
     *
     * @return Collection<int, User>
     */
    public function partyUsers(): Collection
    {
        $this->loadMissing(['user', 'secondUser']);

        return collect([$this->user, $this->secondUser])
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Unique non-empty party emails (case-insensitive dedupe).
     *
     * @return list<string>
     */
    public function partyEmails(): array
    {
        return $this->partyUsers()
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email): string => trim($email))
            ->unique(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }
}
