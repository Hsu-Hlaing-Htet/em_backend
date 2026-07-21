<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Illuminate\Database\Seeder;

class UtilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $rooms = Room::query()
            ->whereIn('status', ['occupied', 'available'])
            ->orderBy('id')
            ->get();

        if (! $admin || $rooms->isEmpty()) {
            $this->command?->warn('Rooms and admin users are required. Run RoomSeeder first.');

            return;
        }

        $utilityTypes = UtilityType::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($utilityTypes->isEmpty()) {
            $this->command?->warn('Utility types are required. Run UtilityTypeSeeder first.');

            return;
        }

        $statuses = array_merge(
            array_fill(0, 6, 'draft'),
            array_fill(0, 5, 'pending'),
            array_fill(0, 10, 'approved'),
            array_fill(0, 4, 'rejected'),
        );

        shuffle($statuses);

        $billingMonths = collect(range(0, 11))
            ->map(fn (int $offset) => now()->subMonths($offset)->format('Y-m-01'))
            ->values();

        $created = 0;
        $roomIndex = 0;

        while ($created < 25) {
            $room = $rooms[$roomIndex % $rooms->count()];
            $billingMonth = $billingMonths[$created % $billingMonths->count()];
            $status = $statuses[$created] ?? 'draft';
            $roomIndex++;

            if (Utility::query()->where('room_id', $room->id)->where('billing_month', $billingMonth)->exists()) {
                continue;
            }

            $utility = Utility::query()->create([
                'room_id' => $room->id,
                'billing_month' => $billingMonth,
                'total_amount' => 0,
                'status' => $status,
                'created_by' => $admin->id,
                'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                'approved_at' => in_array($status, ['approved', 'rejected'], true)
                    ? now()->subDays(fake()->numberBetween(1, 30))
                    : null,
            ]);

            $totalAmount = 0;

            foreach ($utilityTypes->take(3) as $utilityType) {
                $rate = UtilityRate::query()
                    ->where('utility_type_id', $utilityType->id)
                    ->where('status', 'active')
                    ->latest('effective_date')
                    ->first();

                $previousReading = fake()->randomFloat(2, 500, 5000);
                $usage = fake()->randomFloat(2, 20, 350);
                $currentReading = $previousReading + $usage;
                $unitPrice = $rate?->unit_price ?? fake()->randomFloat(4, 50, 500);
                $amount = round($usage * $unitPrice, 2);
                $totalAmount += $amount;

                UtilityItem::query()->create([
                    'utility_id' => $utility->id,
                    'utility_type_id' => $utilityType->id,
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'usage' => $usage,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ]);
            }

            $utility->update(['total_amount' => $totalAmount]);
            $created++;
        }
    }
}
