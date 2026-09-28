<?php

namespace Database\Seeders;

use App\Models\LateFee;
use Database\Seeders\Support\LateFeeSeedSupport;
use Illuminate\Database\Seeder;

class LateFeeSeeder extends Seeder
{
    /**
     * Idempotent demo Late Fee Rules for per-invoice approval snapshots.
     * No global default rule — Admin selects one Active rule per Invoice.
     */
    public function run(): void
    {
        // Rename legacy "Standard Late Fee" in place so FKs / ids stay stable.
        $legacy = LateFee::withTrashed()
            ->where('name', LateFeeSeedSupport::LEGACY_STANDARD)
            ->first();

        if ($legacy) {
            $legacy->fill([
                'name' => LateFeeSeedSupport::RULE_STANDARD,
                'type' => 'fixed',
                'value' => 5000,
                'per' => 'day',
                'grace_days' => 3,
                'status' => 'active',
            ]);
            $legacy->deleted_at = null;
            $legacy->save();
        }

        $lateFees = [
            [
                'name' => LateFeeSeedSupport::RULE_STANDARD,
                'type' => 'fixed',
                'value' => 5000,
                'per' => 'day',
                'grace_days' => 3,
                'status' => 'active',
            ],
            [
                'name' => LateFeeSeedSupport::RULE_EXTENDED,
                'type' => 'fixed',
                'value' => 10000,
                'per' => 'day',
                'grace_days' => 5,
                'status' => 'active',
            ],
            [
                'name' => LateFeeSeedSupport::RULE_PERCENTAGE,
                'type' => 'percentage',
                'value' => 1.5,
                'per' => 'month',
                'grace_days' => 5,
                'status' => 'active',
            ],
        ];

        foreach ($lateFees as $lateFee) {
            $row = LateFee::withTrashed()->firstOrNew(['name' => $lateFee['name']]);
            $row->fill($lateFee);
            $row->deleted_at = null;
            $row->save();
        }
    }
}
