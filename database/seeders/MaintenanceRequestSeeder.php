<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\MaintenanceRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaintenanceRequestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $eligibleContracts = Contract::query()
            ->with(['room', 'user'])
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('room')
            ->whereHas('user')
            ->orderBy('id')
            ->get();

        if (! $admin || $eligibleContracts->isEmpty()) {
            $this->command?->warn('Active/approved contracts are required for maintenance seeding.');

            return;
        }

        $requests = [
            [
                'title' => 'Leaking kitchen faucet',
                'category' => 'plumbing',
                'priority' => 'medium',
                'description' => 'Water drips continuously from the kitchen tap even when fully closed.',
                'status' => 'pending',
            ],
            [
                'title' => 'Air conditioner not cooling',
                'category' => 'hvac',
                'priority' => 'high',
                'description' => 'Bedroom AC runs but does not produce cold air during Yangon afternoon heat.',
                'status' => 'in_progress',
            ],
            [
                'title' => 'Broken balcony door lock',
                'category' => 'general',
                'priority' => 'medium',
                'description' => 'Balcony sliding door lock is stuck and cannot be secured properly.',
                'status' => 'completed',
                'resolution_note' => 'Lock assembly replaced and tested. Tenant confirmed secure closing.',
            ],
            [
                'title' => 'Power outlet sparking',
                'category' => 'electrical',
                'priority' => 'high',
                'description' => 'Living room outlet sparks when plugging in appliances. Needs urgent inspection.',
                'status' => 'rejected',
                'rejection_reason' => 'Duplicate of an earlier ticket already scheduled with the building electrician.',
            ],
        ];

        foreach ($requests as $index => $request) {
            $contract = $eligibleContracts[$index % $eligibleContracts->count()];

            MaintenanceRequest::query()->create([
                'room_id' => $contract->room_id,
                'user_id' => $contract->user_id,
                'created_by' => $contract->user_id,
                'approved_by' => in_array($request['status'], ['in_progress', 'completed', 'rejected'], true)
                    ? $admin->id
                    : null,
                'approved_at' => in_array($request['status'], ['in_progress', 'completed', 'rejected'], true)
                    ? now()->subDays(2)
                    : null,
                'title' => $request['title'],
                'category' => $request['category'],
                'priority' => $request['priority'],
                'description' => $request['description'],
                'status' => $request['status'],
                'rejection_reason' => $request['rejection_reason'] ?? null,
                'resolution_note' => $request['resolution_note'] ?? null,
            ]);
        }

        $this->command?->info('Seeded maintenance requests only for active/approved contract rooms.');
    }
}
