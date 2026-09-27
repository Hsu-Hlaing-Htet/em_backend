<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Idempotent bulk BL-* maintenance tickets (demo volume).
 *
 * Keys on unique title (BL-001 …). updateOrCreate only — no truncate of
 * non-BL rows beyond removing obsolete BL-* titles from earlier cycles.
 * Does not create or modify contracts, invoices, payments, or receipts.
 */
class BulkMaintenanceSeeder extends Seeder
{
    private const BULK_MAINTENANCE_COUNT = 200;

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->first();
        if (! $admin) {
            $this->command?->warn('BulkMaintenanceSeeder skipped: admin user missing.');

            return;
        }

        $categoriesBySlug = MaintenanceCategory::query()
            ->where('status', MaintenanceCategory::STATUS_ACTIVE)
            ->get()
            ->keyBy('slug');

        if ($categoriesBySlug->isEmpty()) {
            $this->command?->warn('Bulk maintenance skipped: active maintenance categories missing.');

            return;
        }

        $eligible = Contract::query()
            ->with(['room', 'user', 'secondUser'])
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereHas('room')
            ->whereHas('user')
            ->orderBy('id')
            ->get()
            ->values();

        if ($eligible->isEmpty()) {
            $this->command?->warn('Bulk maintenance skipped: no active contracts with rooms.');

            return;
        }

        $catalog = $this->bulkMaintenanceCatalog();
        $jointContract = $eligible->first(fn (Contract $contract) => filled($contract->second_user_id));

        $expectedTitles = [];
        $synced = 0;

        for ($i = 0; $i < self::BULK_MAINTENANCE_COUNT; $i++) {
            $scenario = $catalog[$i % count($catalog)];
            $category = $categoriesBySlug->get($scenario['category']);

            if (! $category) {
                continue;
            }

            $reference = sprintf('BL-%03d', $i + 1);
            $title = $reference.' '.$scenario['title'];
            $expectedTitles[] = $title;

            $contract = ($i === 0 && $jointContract)
                ? $jointContract
                : $eligible[$i % $eligible->count()];

            $submitterId = ($i === 0 && $jointContract?->second_user_id)
                ? (int) $jointContract->second_user_id
                : (int) $contract->user_id;

            $createdAt = Carbon::parse('2026-07-01')
                ->addDays(($i * 2) % 85)
                ->setTime(8 + ($i % 10), ($i * 7) % 60, 0);

            $status = $scenario['status'];
            $approvedAt = in_array($status, ['in_progress', 'completed', 'rejected'], true)
                ? $createdAt->copy()->addDays(1 + ($i % 3))
                : null;

            MaintenanceRequest::query()->updateOrCreate(
                ['title' => $title],
                [
                    'room_id' => $contract->room_id,
                    'user_id' => $contract->user_id,
                    'created_by' => $submitterId,
                    'approved_by' => $approvedAt ? $admin->id : null,
                    'approved_at' => $approvedAt,
                    'maintenance_category_id' => $category->id,
                    'category' => $category->slug,
                    'priority' => $scenario['priority'],
                    'description' => $scenario['description'],
                    'status' => $status,
                    'resolution_note' => $status === 'completed'
                        ? ($scenario['resolution_note'] ?? 'Issue resolved and verified with the resident.')
                        : null,
                    'rejection_reason' => $status === 'rejected'
                        ? ($scenario['rejection_reason'] ?? 'Duplicate ticket already scheduled with the building technician.')
                        : null,
                    'created_at' => $createdAt,
                    'updated_at' => $approvedAt ?? $createdAt,
                ],
            );

            $synced++;
        }

        $removed = MaintenanceRequest::query()
            ->where('title', 'like', 'BL-%')
            ->when(
                $expectedTitles !== [],
                fn ($query) => $query->whereNotIn('title', $expectedTitles),
            )
            ->delete();

        $this->command?->info(
            "Bulk maintenance ensured/synced: {$synced} (BL-001…BL-"
            .str_pad((string) self::BULK_MAINTENANCE_COUNT, 3, '0', STR_PAD_LEFT)
            .'); obsolete BL rows removed: '.$removed
        );
    }

    /**
     * @return list<array{
     *     title: string,
     *     category: string,
     *     priority: string,
     *     status: string,
     *     description: string,
     *     resolution_note?: string|null,
     *     rejection_reason?: string|null
     * }>
     */
    private function bulkMaintenanceCatalog(): array
    {
        return [
            [
                'title' => 'Leaking kitchen faucet',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'completed',
                'description' => 'Water drips continuously from the kitchen tap even when fully closed.',
                'resolution_note' => 'Washer replaced and faucet resealed. Resident confirmed no further drip.',
            ],
            [
                'title' => 'AC not cooling properly',
                'category' => 'hvac',
                'priority' => 'high',
                'status' => 'in_progress',
                'description' => 'Bedroom air conditioner runs but does not produce cold air during afternoon heat.',
            ],
            [
                'title' => 'Corridor light flickering',
                'category' => 'electrical',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Common corridor light outside the unit flickers every few seconds after dark.',
            ],
            [
                'title' => 'Balcony door lock stuck',
                'category' => 'general',
                'priority' => 'high',
                'status' => 'pending',
                'description' => 'Balcony sliding door lock is jammed and the door cannot be secured overnight.',
            ],
            [
                'title' => 'Water heater intermittent',
                'category' => 'appliance',
                'priority' => 'medium',
                'status' => 'completed',
                'description' => 'Bathroom water heater works briefly then shuts off before the tank is hot.',
                'resolution_note' => 'Thermostat reset and heating element checked. Hot water restored.',
            ],
            [
                'title' => 'Bathroom drain clogged',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Water drains very slowly and begins backing up after a few minutes of use.',
            ],
            [
                'title' => 'Socket sparking near TV',
                'category' => 'electrical',
                'priority' => 'high',
                'status' => 'in_progress',
                'description' => 'Living room outlet sparks when the TV plug is inserted. Outlet is currently unused.',
            ],
            [
                'title' => 'Ceiling paint peeling',
                'category' => 'general',
                'priority' => 'low',
                'status' => 'pending',
                'description' => 'Paint is peeling near the living room ceiling corner with no active leak visible.',
            ],
            [
                'title' => 'Toilet not flushing',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'in_progress',
                'description' => 'Master bathroom toilet handle moves but the cistern does not release water.',
            ],
            [
                'title' => 'Refrigerator not cooling',
                'category' => 'appliance',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Kitchen refrigerator runs loudly but the freezer compartment is no longer cold.',
            ],
            [
                'title' => 'Air conditioner making noise',
                'category' => 'hvac',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Living room AC makes a repeating rattle when the fan is on medium speed.',
                'resolution_note' => 'Loose outdoor bracket tightened. Noise no longer present.',
            ],
            [
                'title' => 'Power outlet not working',
                'category' => 'electrical',
                'priority' => 'medium',
                'status' => 'rejected',
                'description' => 'Bedroom wall outlet has no power while neighboring outlets still work.',
                'rejection_reason' => 'Duplicate of an earlier ticket already scheduled with the building electrician.',
            ],
            [
                'title' => 'Low water pressure',
                'category' => 'plumbing',
                'priority' => 'medium',
                'status' => 'pending',
                'description' => 'Shower water pressure drops sharply between 06:00 and 08:00 each morning.',
            ],
            [
                'title' => 'Washing machine not starting',
                'category' => 'appliance',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Washer powers on but the start button does not begin a wash cycle.',
                'resolution_note' => 'Door latch sensor cleaned and cycle restarted successfully.',
            ],
            [
                'title' => 'AC leaking water',
                'category' => 'hvac',
                'priority' => 'high',
                'status' => 'pending',
                'description' => 'Indoor AC unit drips water onto the bedroom floor after about 30 minutes of use.',
            ],
            [
                'title' => 'Cabinet hinge loose',
                'category' => 'general',
                'priority' => 'low',
                'status' => 'completed',
                'description' => 'Kitchen cabinet door hangs unevenly and scrapes the frame when opened.',
                'resolution_note' => 'Hinge screws tightened and door realigned.',
            ],
        ];
    }
}
