<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\MaintenanceRequest;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaintenanceRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $occupiedRooms = Room::query()->where('status', 'occupied')->get();

        if (! $admin || $occupiedRooms->isEmpty()) {
            $this->command?->warn('Occupied rooms and admin users are required. Run RoomSeeder and UserSeeder first.');

            return;
        }

        $requests = [
            [
                'title' => 'Leaking kitchen faucet',
                'description' => 'Water drips continuously from the kitchen tap even when fully closed.',
                'status' => 'pending',
            ],
            [
                'title' => 'Air conditioner not cooling',
                'description' => 'Bedroom AC runs but does not produce cold air. Last serviced over a year ago.',
                'status' => 'in_progress',
            ],
            [
                'title' => 'Broken balcony door lock',
                'description' => 'Balcony sliding door lock is stuck and cannot be secured properly.',
                'status' => 'completed',
            ],
            [
                'title' => 'Power outlet sparking',
                'description' => 'Living room outlet sparks when plugging in appliances. Needs urgent inspection.',
                'status' => 'rejected',
            ],
        ];

        foreach ($requests as $index => $request) {
            $room = $occupiedRooms[$index % $occupiedRooms->count()];

            $contract = Contract::query()
                ->where('room_id', $room->id)
                ->where('status', 'active')
                ->first();

            $customerId = $contract?->user_id ?? User::query()
                ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
                ->value('id');

            if (! $customerId) {
                continue;
            }

            MaintenanceRequest::query()->create([
                'room_id' => $room->id,
                'user_id' => $customerId,
                'created_by' => $customerId,
                'approved_by' => in_array($request['status'], ['in_progress', 'completed', 'rejected'], true)
                    ? $admin->id
                    : null,
                'approved_at' => in_array($request['status'], ['in_progress', 'completed', 'rejected'], true)
                    ? now()->subDays(2)
                    : null,
                'title' => $request['title'],
                'description' => $request['description'],
                'status' => $request['status'],
            ]);
        }
    }
}
