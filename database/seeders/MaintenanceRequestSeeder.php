<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MaintenanceRequestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->first();

        $categoriesBySlug = MaintenanceCategory::query()
            ->where('status', MaintenanceCategory::STATUS_ACTIVE)
            ->get()
            ->keyBy('slug');

        $eligibleContracts = Contract::query()
            ->with(['room', 'user', 'secondUser'])
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereHas('room')
            ->whereHas('user')
            ->orderBy('id')
            ->get();

        if (! $admin || $eligibleContracts->isEmpty() || $categoriesBySlug->isEmpty()) {
            $this->command?->warn('Active contracts and maintenance categories are required for maintenance seeding.');

            return;
        }

        $requests = [
            [
                'title' => 'Leaking kitchen faucet',
                'category' => 'plumbing',
                'priority' => 'medium',
                'description' => 'Water drips continuously from the kitchen tap even when fully closed.',
                'status' => 'pending',
                'created_at' => '2026-08-12 10:00:00',
            ],
            [
                'title' => 'Air conditioner not cooling',
                'category' => 'hvac',
                'priority' => 'high',
                'description' => 'Bedroom AC runs but does not produce cold air during Yangon afternoon heat.',
                'status' => 'in_progress',
                'created_at' => '2026-08-20 15:30:00',
            ],
            [
                'title' => 'Broken balcony door lock',
                'category' => 'general',
                'priority' => 'high',
                'description' => 'Balcony sliding door lock is stuck and cannot be secured properly.',
                'status' => 'completed',
                'resolution_note' => 'Lock assembly replaced and tested. Resident confirmed secure closing.',
                'created_at' => '2026-07-18 09:20:00',
            ],
            [
                'title' => 'Power outlet sparking',
                'category' => 'electrical',
                'priority' => 'high',
                'description' => 'Living room outlet sparks when plugging in appliances. Needs urgent inspection.',
                'status' => 'rejected',
                'rejection_reason' => 'Duplicate of an earlier ticket already scheduled with the building electrician.',
                'created_at' => '2026-08-05 11:45:00',
            ],
        ];

        $jointContract = $eligibleContracts->first(fn (Contract $contract) => filled($contract->second_user_id));

        foreach ($requests as $index => $request) {
            $category = $categoriesBySlug->get($request['category']);

            if (! $category) {
                continue;
            }

            $contract = ($index === 0 && $jointContract)
                ? $jointContract
                : $eligibleContracts[$index % $eligibleContracts->count()];

            $createdAt = Carbon::parse($request['created_at']);
            $approvedAt = in_array($request['status'], ['in_progress', 'completed', 'rejected'], true)
                ? $createdAt->copy()->addDays(2)
                : null;

            MaintenanceRequest::query()->updateOrCreate(
                [
                    'title' => $request['title'],
                    'room_id' => $contract->room_id,
                    'user_id' => $contract->user_id,
                ],
                [
                    'created_by' => ($index === 1 && $contract->second_user_id)
                        ? $contract->second_user_id
                        : $contract->user_id,
                    'approved_by' => $approvedAt ? $admin->id : null,
                    'approved_at' => $approvedAt,
                    'maintenance_category_id' => $category->id,
                    'category' => $category->slug,
                    'priority' => $request['priority'],
                    'description' => $request['description'],
                    'status' => $request['status'],
                    'rejection_reason' => $request['rejection_reason'] ?? null,
                    'resolution_note' => $request['resolution_note'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $approvedAt ?? $createdAt,
                ],
            );
        }

        $this->command?->info('Seeded maintenance requests only for active/approved contract rooms.');
    }
}
