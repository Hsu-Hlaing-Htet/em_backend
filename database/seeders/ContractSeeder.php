<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\PaymentPlan;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
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
            ->orderBy('id')
            ->get();

        if (! $admin || $customers->isEmpty()) {
            $this->command?->warn('Users must be seeded before contracts. Run UserSeeder first.');

            return;
        }

        $fullPaymentPlan = PaymentPlan::query()->where('payment_type', 'full')->first();
        $installmentPlan = PaymentPlan::query()
            ->where('payment_type', 'installment')
            ->where('status', 'active')
            ->first();

        $saleRooms = Room::query()
            ->whereIn('type', ['sale', 'both'])
            ->where('status', 'available')
            ->orderBy('id')
            ->take(15)
            ->get();

        $rentRooms = Room::query()
            ->whereIn('type', ['rent', 'both'])
            ->where('status', 'available')
            ->orderBy('id')
            ->take(20)
            ->get();

        if ($saleRooms->count() < 15 || $rentRooms->count() < 20) {
            $this->command?->warn('Not enough available rooms for contracts. Run RoomSeeder first.');

            return;
        }

        $saleStatuses = array_merge(
            array_fill(0, 3, 'draft'),
            array_fill(0, 2, 'pending'),
            array_fill(0, 8, 'approved'),
            array_fill(0, 2, 'rejected'),
        );

        $rentStatuses = array_merge(
            array_fill(0, 3, 'draft'),
            array_fill(0, 2, 'pending'),
            array_fill(0, 12, 'active'),
            array_fill(0, 3, 'rejected'),
        );

        foreach ($saleRooms as $index => $room) {
            $status = $saleStatuses[$index] ?? 'draft';
            $customer = $customers[$index % $customers->count()];
            $useInstallment = $index % 3 === 0 && $installmentPlan;
            $isApproved = $status === 'approved';

            Contract::query()->create([
                'contract_number' => 'S-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'room_id' => $room->id,
                'payment_plan_id' => $useInstallment ? $installmentPlan->id : $fullPaymentPlan?->id,
                'created_by' => $admin->id,
                'approved_by' => $isApproved ? $admin->id : ($status === 'rejected' ? $admin->id : null),
                'approved_at' => in_array($status, ['approved', 'rejected'], true)
                    ? now()->subDays(fake()->numberBetween(5, 90))
                    : null,
                'contract_total' => $room->sale_price,
                'deposit_amount' => $room->booking_deposit_price,
                'type' => 'sale',
                'payment_type' => $useInstallment ? 'installment' : 'full',
                'duration_months' => $useInstallment ? $installmentPlan->duration_months : null,
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => $useInstallment ? now()->addMonths($installmentPlan->duration_months)->toDateString() : null,
                'billing_day' => $useInstallment ? fake()->numberBetween(1, 28) : null,
                'status' => $status,
                'remark' => match ($status) {
                    'draft' => 'Sale draft awaiting customer confirmation.',
                    'pending' => 'Submitted for management approval.',
                    'approved' => 'Approved sale contract.',
                    default => 'Rejected due to incomplete documentation.',
                },
            ]);

            if ($isApproved) {
                $room->update(['status' => 'sold']);
            }
        }

        foreach ($rentRooms as $index => $room) {
            $status = $rentStatuses[$index] ?? 'draft';
            $customer = $customers[($index + $saleRooms->count()) % $customers->count()];
            $useInstallment = $index % 4 === 0 && $installmentPlan;
            $isActive = $status === 'active';

            Contract::query()->create([
                'contract_number' => 'R-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'user_id' => $customer->id,
                'room_id' => $room->id,
                'payment_plan_id' => $useInstallment ? $installmentPlan->id : null,
                'created_by' => $admin->id,
                'approved_by' => in_array($status, ['active', 'rejected'], true) ? $admin->id : null,
                'approved_at' => in_array($status, ['active', 'rejected'], true)
                    ? now()->subDays(fake()->numberBetween(5, 90))
                    : null,
                'contract_total' => $useInstallment
                    ? round($room->rent_price * ($installmentPlan->duration_months ?? 12), 2)
                    : round($room->rent_price * 12, 2),
                'deposit_amount' => $room->rent_deposit_price,
                'type' => 'rent',
                'payment_type' => $useInstallment ? 'installment' : 'full',
                'duration_months' => $useInstallment ? $installmentPlan->duration_months : null,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'billing_day' => $useInstallment ? fake()->numberBetween(1, 28) : null,
                'status' => $status,
                'remark' => match ($status) {
                    'draft' => 'Rent draft pending tenant review.',
                    'pending' => 'Awaiting lease approval.',
                    'active' => 'Active rental agreement.',
                    default => 'Rejected due to failed background check.',
                },
            ]);

            if ($isActive) {
                $room->update(['status' => 'occupied']);
            }
        }
    }
}
