<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\Support\CustomerHistoryProfiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ContractSeeder extends Seeder
{
    private int $saleSequence = 0;

    private int $rentSequence = 0;

    /** @var list<int> */
    private array $usedRoomIds = [];

    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $customers = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->get()
            ->keyBy('email');

        if (! $admin || $customers->isEmpty()) {
            $this->command?->warn('Users must be seeded before contracts. Run UserSeeder first.');

            return;
        }

        $fullPaymentPlan = PaymentPlan::query()->where('payment_type', 'full')->first();
        $installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->where('duration_months', 12)
            ->first();

        $profiles = CustomerHistoryProfiles::emailsByPersona();

        $occupiedRentNumbers = ['A-501', 'D-402', 'D-403', 'C-501', 'A-801', 'D-601'];
        $reservedSaleNumbers = ['C-301', 'C-302', 'F-102'];
        $soldSaleNumbers = ['E-701', 'E-702', 'E-203'];
        $keepAvailableNumbers = ['A-101', 'A-102', 'B-201', 'B-202', 'B-305'];

        $takeRent = fn (array $preferred = []) => $this->takeRoom('rent', $preferred, $keepAvailableNumbers);
        $takeSale = fn (array $preferred = []) => $this->takeRoom('sale', $preferred, $keepAvailableNumbers);

        foreach ($profiles['active_rent'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $takeRent($occupiedRentNumbers);

            if (! $customer || ! $room) {
                continue;
            }

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'active',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index === 1 ? $installmentPlan : null,
                startDate: now()->subMonths(6),
                endDate: now()->addMonths(6),
                remark: 'Active rental agreement with monthly MMK billing.',
                roomStatus: 'occupied',
            );
        }

        foreach ($profiles['former_rent'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $takeRent();

            if (! $customer || ! $room) {
                continue;
            }

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'completed',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index === 0 ? $installmentPlan : null,
                startDate: now()->subMonths(18),
                endDate: now()->subMonths(6),
                remark: 'Lease completed. Security deposit returned and unit released.',
                roomStatus: 'available',
            );
        }

        $pendingSaleCustomer = $customers->get($profiles['pipeline'][0] ?? '') ?? $customers->first();
        $pendingSaleRoom = $takeSale($reservedSaleNumbers);
        if ($pendingSaleCustomer && $pendingSaleRoom) {
            $this->createSaleContract(
                admin: $admin,
                customer: $pendingSaleCustomer,
                room: $pendingSaleRoom,
                status: 'pending',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: null,
                startDate: now()->subWeeks(2),
                endDate: null,
                remark: 'Sale application pending management approval.',
                roomStatus: 'reserved',
            );
        }

        foreach ($profiles['active_sale'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $takeSale($reservedSaleNumbers);

            if (! $customer || ! $room) {
                continue;
            }

            $this->createSaleContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'approved',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index === 0 ? $installmentPlan : null,
                startDate: now()->subMonths(2),
                endDate: $index === 0 ? now()->addMonths(10) : null,
                remark: 'Approved sale contract. Unit reserved pending completion.',
                roomStatus: 'reserved',
            );
        }

        foreach ($profiles['former_sale'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $takeSale($soldSaleNumbers);

            if (! $customer || ! $room) {
                continue;
            }

            $this->createSaleContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'completed',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: null,
                startDate: now()->subMonths(20),
                endDate: now()->subMonths(12),
                remark: 'Sale completed. Full purchase amount received and title transferred.',
                roomStatus: 'sold',
            );
        }

        foreach (['draft', 'pending', 'rejected'] as $index => $status) {
            $customer = $customers->get($profiles['pipeline'][$index + 1] ?? '') ?? $customers->first();
            $room = $takeRent();

            if (! $customer || ! $room) {
                continue;
            }

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: $status,
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: null,
                startDate: now()->subMonths(1),
                endDate: now()->addMonths(11),
                remark: match ($status) {
                    'draft' => 'Rent draft pending tenant review.',
                    'pending' => 'Awaiting lease approval.',
                    default => 'Rejected due to incomplete documentation.',
                },
                roomStatus: 'available',
            );
        }

        $availableCount = Room::query()->where('status', 'available')->count();
        $this->command?->info("Contracts seeded. Available rooms remaining: {$availableCount}");
    }

    /**
     * @param  list<string>  $preferredNumbers
     * @param  list<string>  $keepAvailableNumbers
     */
    private function takeRoom(string $kind, array $preferredNumbers = [], array $keepAvailableNumbers = []): ?Room
    {
        $types = $kind === 'sale' ? ['sale', 'both'] : ['rent', 'both'];

        $query = Room::query()
            ->whereIn('type', $types)
            ->whereNotIn('id', $this->usedRoomIds)
            ->whereNotIn('room_number', $keepAvailableNumbers)
            ->orderBy('id');

        $room = null;

        if ($preferredNumbers !== []) {
            $room = (clone $query)->whereIn('room_number', $preferredNumbers)->first();
        }

        $room ??= $query->first();

        if ($room) {
            $this->usedRoomIds[] = $room->id;
        }

        return $room;
    }

    private function createSaleContract(
        User $admin,
        User $customer,
        Room $room,
        string $status,
        ?PaymentPlan $fullPaymentPlan,
        ?PaymentPlan $installmentPlan,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate,
        string $remark,
        string $roomStatus,
    ): Contract {
        $useInstallment = $installmentPlan !== null;
        $plan = $useInstallment ? $installmentPlan : $fullPaymentPlan;

        $contract = Contract::query()->create([
            'contract_number' => 'S-'.str_pad((string) (++$this->saleSequence), 6, '0', STR_PAD_LEFT),
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => in_array($status, ['approved', 'completed', 'rejected'], true) ? $admin->id : null,
            'approved_at' => in_array($status, ['approved', 'completed', 'rejected'], true) ? $startDate : null,
            'contract_total' => $room->sale_price,
            'deposit_amount' => $room->booking_deposit_price,
            'type' => 'sale',
            'payment_type' => $useInstallment ? 'installment' : 'full',
            'duration_months' => $useInstallment ? $installmentPlan->duration_months : null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_day' => $useInstallment ? 5 : null,
            'status' => $status,
            'remark' => $remark,
        ]);

        $room->update(['status' => $roomStatus]);

        return $contract;
    }

    private function createRentContract(
        User $admin,
        User $customer,
        Room $room,
        string $status,
        ?PaymentPlan $fullPaymentPlan,
        ?PaymentPlan $installmentPlan,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $remark,
        string $roomStatus,
    ): Contract {
        $useInstallment = $installmentPlan !== null;
        $plan = $useInstallment ? $installmentPlan : $fullPaymentPlan;
        $durationMonths = $useInstallment
            ? $installmentPlan->duration_months
            : max(1, (int) ceil($startDate->diff($endDate)->days / 30));

        $contract = Contract::query()->create([
            'contract_number' => 'R-'.str_pad((string) (++$this->rentSequence), 6, '0', STR_PAD_LEFT),
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => in_array($status, ['active', 'completed', 'rejected'], true) ? $admin->id : null,
            'approved_at' => in_array($status, ['active', 'completed', 'rejected'], true) ? $startDate : null,
            'contract_total' => round($room->rent_price * $durationMonths, 2),
            'deposit_amount' => $room->rent_deposit_price,
            'type' => 'rent',
            'payment_type' => $useInstallment ? 'installment' : 'full',
            'duration_months' => $useInstallment ? $installmentPlan->duration_months : $durationMonths,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_day' => 5,
            'status' => $status,
            'remark' => $remark,
        ]);

        $room->update(['status' => $roomStatus]);

        return $contract;
    }
}
