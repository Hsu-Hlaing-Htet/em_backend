<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $started = microtime(true);

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            PaymentPlanSeeder::class,
            ChargeTypeSeeder::class,
            PaymentMethodSeeder::class,
            LateFeeSeeder::class,
            UtilityTypeSeeder::class,
            UtilityRateSeeder::class,
            WorkflowScenarioSeeder::class,
            BulkDemoSeeder::class,
            RoomImageSeeder::class,
        ]);

        $this->command?->info('Seeded login credentials:');
        $this->command?->table(
            ['Role', 'Name', 'Email', 'Password'],
            collect(UserSeeder::credentials())->take(5)->all()
        );

        $this->command?->info('Customer history personas (password: p@ssword for all):');
        $this->command?->table(
            ['Persona', 'Email'],
            [
                ['Active rent (full billing matrix)', 'mgmg@rosewoodroyale.com'],
                ['Active sale (approved/reserved)', 'susu@rosewoodroyale.com'],
                ['Former rent (completed)', 'zawzaw@rosewoodroyale.com'],
                ['Former sale (completed/sold)', 'nwenwe@rosewoodroyale.com'],
                ['Registered only (no contract/maintenance)', 'tuntun@rosewoodroyale.com'],
                ['Secondary active rent (approved payment, no receipt)', 'hlahla@rosewoodroyale.com'],
                ['Bulk customers', 'bulk021@rosewoodroyale.com … bulkNNN@rosewoodroyale.com'],
            ]
        );

        $this->reportVolumeSummary();
        $this->command?->info('Total seed execution time: '.round(microtime(true) - $started, 2).'s');
    }

    private function reportVolumeSummary(): void
    {
        $tables = [
            'users' => User::query()->count(),
            'profiles' => Profile::query()->count(),
            'buildings' => Building::query()->count(),
            'rooms' => Room::query()->count(),
            'room_images' => RoomImage::query()->count(),
            'contracts' => Contract::query()->count(),
            'utilities' => Utility::query()->count(),
            'utility_items' => UtilityItem::query()->count(),
            'invoices' => Invoice::query()->count(),
            'invoice_items' => InvoiceItem::query()->count(),
            'payments' => Payment::query()->count(),
            'receipts' => Receipt::query()->count(),
            'maintenance_requests' => MaintenanceRequest::query()->count(),
        ];

        $total = array_sum($tables);

        $this->command?->info('Volume summary (demo domain tables):');
        $this->command?->table(
            ['Table', 'Count'],
            collect($tables)->map(fn ($count, $table) => [$table, $count])->values()->all()
        );
        $this->command?->info("Domain total: {$total}");

        $this->command?->info('Status breakdown:');
        $this->command?->table(
            ['Metric', 'Count'],
            [
                ['Customers', User::query()->whereHas('role', fn ($q) => $q->where('name', 'customer'))->count()],
                ['Rooms available/reserved/occupied/sold/maintenance',
                    Room::query()->where('status', 'available')->count().'/'
                    .Room::query()->where('status', 'reserved')->count().'/'
                    .Room::query()->where('status', 'occupied')->count().'/'
                    .Room::query()->where('status', 'sold')->count().'/'
                    .Room::query()->where('status', 'maintenance')->count()],
                ['Contracts draft/pending/active/approved/completed/rejected',
                    Contract::query()->where('status', 'draft')->count().'/'
                    .Contract::query()->where('status', 'pending')->count().'/'
                    .Contract::query()->where('status', 'active')->count().'/'
                    .Contract::query()->where('status', 'approved')->count().'/'
                    .Contract::query()->where('status', 'completed')->count().'/'
                    .Contract::query()->where('status', 'rejected')->count()],
                ['Invoices draft/issued/partial/paid/overdue',
                    Invoice::query()->where('status', 'draft')->count().'/'
                    .Invoice::query()->where('status', 'issued')->count().'/'
                    .Invoice::query()->where('status', 'partial')->count().'/'
                    .Invoice::query()->where('status', 'paid')->count().'/'
                    .Invoice::query()->where('status', 'overdue')->count()],
                ['Payments pending/approved/rejected',
                    Payment::query()->where('status', 'pending')->count().'/'
                    .Payment::query()->where('status', 'approved')->count().'/'
                    .Payment::query()->where('status', 'rejected')->count()],
                ['Receipts approval pending/approved/rejected',
                    Receipt::query()->where('approval_status', 'pending')->count().'/'
                    .Receipt::query()->where('approval_status', 'approved')->count().'/'
                    .Receipt::query()->where('approval_status', 'rejected')->count()],
                ['Maintenance pending/in_progress/completed/rejected',
                    MaintenanceRequest::query()->where('status', 'pending')->count().'/'
                    .MaintenanceRequest::query()->where('status', 'in_progress')->count().'/'
                    .MaintenanceRequest::query()->where('status', 'completed')->count().'/'
                    .MaintenanceRequest::query()->where('status', 'rejected')->count()],
                ['Contracts expiring within 60 days',
                    Contract::query()->whereIn('status', ['active', 'approved'])
                        ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
                        ->count()],
                ['Rooms with <2 images', Room::query()->has('roomImages', '<', 2)->count()],
                ['Pending/rejected payments with receipt',
                    Payment::query()->whereIn('status', ['pending', 'rejected'])->whereHas('receipt')->count()],
                ['Invoice total mismatches',
                    Invoice::query()->withSum('items', 'amount')->get()
                        ->filter(fn (Invoice $invoice) => round((float) $invoice->total_amount, 2) !== round((float) ($invoice->items_sum_amount ?? 0), 2))
                        ->count()],
            ]
        );

        // Keep FK sanity check cheap.
        $orphanPayments = DB::table('payments')
            ->leftJoin('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->whereNull('invoices.id')
            ->count();
        $orphanReceipts = DB::table('receipts')
            ->leftJoin('payments', 'receipts.payment_id', '=', 'payments.id')
            ->whereNull('payments.id')
            ->count();

        $this->command?->info("Orphan payments: {$orphanPayments}; orphan receipts: {$orphanReceipts}");
    }
}
