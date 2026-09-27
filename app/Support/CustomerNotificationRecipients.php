<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Support\Collection;

/**
 * Resolve CURRENT customer account emails for outbound notifications.
 *
 * Authoritative source: users.email on linked contract parties (and payer when relevant).
 * Admin UI / request email overrides are not used for delivery — they can be stale after
 * a customer email change.
 */
final class CustomerNotificationRecipients
{
    /**
     * @return list<string>
     */
    public static function emailsForContract(?Contract $contract): array
    {
        return self::usersForContract($contract)
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email): string => trim($email))
            ->unique(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }

    /**
     * Fresh party users (Customer 1 + optional Customer 2) with current emails.
     *
     * @return Collection<int, User>
     */
    public static function usersForContract(?Contract $contract): Collection
    {
        if (! $contract) {
            return collect();
        }

        // Drop any previously eager-loaded party models that may hold a stale email.
        $contract->unsetRelation('user');
        $contract->unsetRelation('secondUser');
        $contract->load(['user', 'secondUser']);

        return $contract->partyUsers();
    }

    /**
     * @return list<string>
     */
    public static function emailsForInvoice(Invoice $invoice): array
    {
        $invoice->unsetRelation('contract');
        $invoice->load(['contract']);

        return self::emailsForContract($invoice->contract);
    }

    /**
     * @return array{emails: list<string>, users: Collection<int, User>}
     */
    public static function forUtility(Utility $utility, ?User $fallbackOccupant = null): array
    {
        $utility->unsetRelation('contract');
        $utility->load(['contract']);

        $contract = $utility->contract;

        // Utilities may only store room_id; resolve the active room contract for parties.
        if (! $contract && $utility->room_id) {
            $contract = Contract::query()
                ->where('room_id', $utility->room_id)
                ->where('status', Contract::STATUS_ACTIVE)
                ->latest('id')
                ->first();
        }

        if ($contract) {
            $users = self::usersForContract($contract);

            return [
                'emails' => self::emailsForContract($contract),
                'users' => $users,
            ];
        }

        if ($fallbackOccupant?->id) {
            $fresh = User::query()->find($fallbackOccupant->id) ?? $fallbackOccupant;
            $email = trim((string) ($fresh->email ?? ''));

            return [
                'emails' => $email !== '' ? [$email] : [],
                'users' => collect([$fresh])->filter(),
            ];
        }

        return [
            'emails' => [],
            'users' => collect(),
        ];
    }
}
