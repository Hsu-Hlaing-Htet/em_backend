<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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

        if (! $admin) {
            $this->command?->warn('Admin user is required. Run UserSeeder first.');

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

        $rentContracts = Contract::query()
            ->where('type', 'rent')
            ->whereIn('status', ['active', 'completed'])
            ->with('room')
            ->orderBy('id')
            ->get();

        if ($rentContracts->isEmpty()) {
            $this->command?->warn('Rent contracts are required. Run ContractSeeder first.');

            return;
        }

        foreach ($rentContracts as $contract) {
            $months = $this->billingMonthsForContract($contract);

            foreach ($months as $monthIndex => $billingMonth) {
                if (Utility::query()
                    ->where('room_id', $contract->room_id)
                    ->whereDate('billing_month', $billingMonth->toDateString())
                    ->exists()) {
                    continue;
                }

                $status = $this->utilityStatusFor($contract, $monthIndex, $months->count());

                $utility = Utility::query()->create([
                    'room_id' => $contract->room_id,
                    'billing_month' => $billingMonth->toDateString(),
                    'total_amount' => 0,
                    'status' => $status,
                    'created_by' => $admin->id,
                    'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                    'approved_at' => in_array($status, ['approved', 'rejected'], true)
                        ? $billingMonth->copy()->endOfMonth()
                        : null,
                ]);

                $totalAmount = $this->seedUtilityItems($utility, $utilityTypes, $contract, $monthIndex);
                $utility->update(['total_amount' => $totalAmount]);
            }
        }

        $this->seedPipelineUtilities($admin, $utilityTypes);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function billingMonthsForContract(Contract $contract): Collection
    {
        $start = Carbon::parse($contract->start_date)->startOfMonth();
        $end = match ($contract->status) {
            'completed' => Carbon::parse($contract->end_date)->startOfMonth(),
            default => now()->startOfMonth(),
        };

        $months = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months;
    }

    private function utilityStatusFor(Contract $contract, int $monthIndex, int $totalMonths): string
    {
        if ($contract->status === 'completed') {
            return 'approved';
        }

        if ($monthIndex === $totalMonths - 1) {
            return 'pending';
        }

        if ($monthIndex === $totalMonths - 2) {
            return 'draft';
        }

        return 'approved';
    }

    /**
     * @param  Collection<int, UtilityType>  $utilityTypes
     */
    private function seedUtilityItems(
        Utility $utility,
        Collection $utilityTypes,
        Contract $contract,
        int $monthIndex,
    ): float {
        $totalAmount = 0;
        $baseReading = 1200 + ($contract->room_id * 10) + ($monthIndex * 35);

        foreach ($utilityTypes->take(3) as $utilityTypeIndex => $utilityType) {
            $rate = UtilityRate::query()
                ->where('utility_type_id', $utilityType->id)
                ->where('status', 'active')
                ->latest('effective_date')
                ->first();

            $previousReading = $baseReading + ($utilityTypeIndex * 400);
            $usage = match ($utilityTypeIndex) {
                0 => fake()->randomFloat(2, 80, 180),
                1 => fake()->randomFloat(2, 120, 320),
                default => fake()->randomFloat(2, 15, 45),
            };
            $currentReading = $previousReading + $usage;
            $unitPrice = $rate?->unit_price ?? fake()->randomFloat(4, 80, 450);
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

        return round($totalAmount, 2);
    }

    /**
     * @param  Collection<int, UtilityType>  $utilityTypes
     */
    private function seedPipelineUtilities(User $admin, Collection $utilityTypes): void
    {
        $statuses = ['draft', 'pending', 'rejected'];
        $rooms = Contract::query()
            ->where('type', 'rent')
            ->whereIn('status', ['draft', 'pending', 'rejected'])
            ->pluck('room_id')
            ->unique()
            ->take(3);

        foreach ($rooms as $index => $roomId) {
            $contract = Contract::query()->where('room_id', $roomId)->first();

            if (! $contract) {
                continue;
            }

            $billingMonth = now()->subMonths($index + 1)->startOfMonth();
            $status = $statuses[$index] ?? 'draft';

            if (Utility::query()->where('room_id', $roomId)->whereDate('billing_month', $billingMonth->toDateString())->exists()) {
                continue;
            }

            $utility = Utility::query()->create([
                'room_id' => $roomId,
                'billing_month' => $billingMonth->toDateString(),
                'total_amount' => 0,
                'status' => $status,
                'created_by' => $admin->id,
                'approved_by' => $status === 'rejected' ? $admin->id : null,
                'approved_at' => $status === 'rejected' ? now()->subDays(3) : null,
            ]);

            $totalAmount = $this->seedUtilityItems($utility, $utilityTypes, $contract, $index);
            $utility->update(['total_amount' => $totalAmount]);
        }
    }
}
