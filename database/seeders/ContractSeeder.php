<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\Support\CustomerHistoryProfiles;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    private int $saleSequence = 0;

    private int $rentSequence = 0;

    /**
     * Run the database seeds.
     */
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

        $saleRooms = Room::query()
            ->whereIn('type', ['sale', 'both'])
            ->where('status', 'available')
            ->orderBy('id')
            ->get();

        $rentRooms = Room::query()
            ->whereIn('type', ['rent', 'both'])
            ->where('status', 'available')
            ->orderBy('id')
            ->get();

        if ($saleRooms->count() < 12 || $rentRooms->count() < 12) {
            $this->command?->warn('Not enough available rooms for contracts. Run RoomSeeder first.');

            return;
        }

        $profiles = CustomerHistoryProfiles::emailsByPersona();
        $saleRoomIndex = 0;
        $rentRoomIndex = 0;

        foreach ($profiles['active_rent'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $rentRooms[$rentRoomIndex++];

            if (! $customer || ! $room) {
                continue;
            }

            $useInstallment = $index === 1 && $installmentPlan;

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'active',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $useInstallment ? $installmentPlan : null,
                startDate: now()->subMonths(6),
                endDate: now()->addMonths(6),
                remark: 'Active rental agreement with monthly billing.',
                occupyRoom: true,
            );
        }

        foreach ($profiles['former_rent'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $rentRooms[$rentRoomIndex++];

            if (! $customer || ! $room) {
                continue;
            }

            $startDate = now()->subMonths(18);
            $endDate = now()->subMonths(6);

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'completed',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index === 0 ? $installmentPlan : null,
                startDate: $startDate,
                endDate: $endDate,
                remark: 'Lease completed. Security deposit returned and unit released.',
                occupyRoom: false,
            );
        }

        foreach ($profiles['active_sale'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $saleRooms[$saleRoomIndex++];

            if (! $customer || ! $room) {
                continue;
            }

            $useInstallment = $index === 2 && $installmentPlan;

            $this->createSaleContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'approved',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $useInstallment ? $installmentPlan : null,
                startDate: now()->subMonths(4),
                endDate: $useInstallment ? now()->addMonths(8) : null,
                remark: 'Approved sale contract with active payment schedule.',
                markRoomSold: true,
            );
        }

        foreach ($profiles['former_sale'] as $index => $email) {
            $customer = $customers->get($email);
            $room = $saleRooms[$saleRoomIndex++];

            if (! $customer || ! $room) {
                continue;
            }

            $startDate = now()->subMonths(24);
            $endDate = now()->subMonths(12);
            $useInstallment = $index === 1 && $installmentPlan;

            $this->createSaleContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: 'completed',
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $useInstallment ? $installmentPlan : null,
                startDate: $startDate,
                endDate: $endDate,
                remark: 'Sale completed. Full purchase amount received and title transferred.',
                markRoomSold: true,
            );
        }

        $pipelineCustomers = collect($profiles['pipeline'])
            ->map(fn (string $email) => $customers->get($email))
            ->filter()
            ->values();

        $salePipelineStatuses = ['draft', 'draft', 'pending', 'pending', 'approved', 'rejected', 'rejected'];
        $rentPipelineStatuses = ['draft', 'draft', 'pending', 'pending', 'active', 'active', 'rejected', 'rejected'];

        foreach ($salePipelineStatuses as $index => $status) {
            $room = $saleRooms[$saleRoomIndex++] ?? null;
            $customer = $pipelineCustomers[$index % max($pipelineCustomers->count(), 1)] ?? null;

            if (! $room || ! $customer) {
                continue;
            }

            $this->createSaleContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: $status,
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index % 3 === 0 ? $installmentPlan : null,
                startDate: now()->subMonths(2),
                endDate: null,
                remark: match ($status) {
                    'draft' => 'Sale draft awaiting customer confirmation.',
                    'pending' => 'Submitted for management approval.',
                    'approved' => 'Approved sale contract in onboarding pipeline.',
                    default => 'Rejected due to incomplete documentation.',
                },
                markRoomSold: $status === 'approved',
            );
        }

        foreach ($rentPipelineStatuses as $index => $status) {
            $room = $rentRooms[$rentRoomIndex++] ?? null;
            $customer = $pipelineCustomers[($index + 2) % max($pipelineCustomers->count(), 1)] ?? null;

            if (! $room || ! $customer) {
                continue;
            }

            $this->createRentContract(
                admin: $admin,
                customer: $customer,
                room: $room,
                status: $status,
                fullPaymentPlan: $fullPaymentPlan,
                installmentPlan: $index % 4 === 0 ? $installmentPlan : null,
                startDate: now()->subMonths(3),
                endDate: now()->addMonths(9),
                remark: match ($status) {
                    'draft' => 'Rent draft pending tenant review.',
                    'pending' => 'Awaiting lease approval.',
                    'active' => 'Active rental agreement in admin pipeline.',
                    default => 'Rejected due to failed background check.',
                },
                occupyRoom: $status === 'active',
            );
        }
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
        bool $markRoomSold,
    ): Contract {
        $useInstallment = $installmentPlan !== null;
        $plan = $useInstallment ? $installmentPlan : $fullPaymentPlan;
        $isApproved = in_array($status, ['approved', 'completed'], true);
        $approvedAt = in_array($status, ['approved', 'completed', 'rejected'], true)
            ? $startDate
            : null;

        $contract = Contract::query()->create([
            'contract_number' => 'S-'.str_pad((string) (++$this->saleSequence), 6, '0', STR_PAD_LEFT),
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'payment_plan_id' => $plan?->id,
            'created_by' => $admin->id,
            'approved_by' => in_array($status, ['approved', 'completed', 'rejected'], true) ? $admin->id : null,
            'approved_at' => $approvedAt,
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

        if ($markRoomSold) {
            $room->update(['status' => 'sold']);
        }

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
        bool $occupyRoom,
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
            'approved_at' => in_array($status, ['active', 'completed', 'rejected'], true)
                ? $startDate
                : null,
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

        if ($occupyRoom) {
            $room->update(['status' => 'occupied']);
        } elseif ($status === 'completed') {
            $room->update(['status' => 'available']);
        }

        return $contract;
    }
}
