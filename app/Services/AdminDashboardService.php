<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function chartMetrics(): array
    {
        return [
            'kpi_stats' => $this->kpiStats(),
            'revenue_summary' => $this->revenueSummary(),
            'revenue_chart' => $this->revenueChart(),
            'property_stats' => $this->propertyStats(),
            'invoice_stats' => $this->invoiceStats(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpiStats(): array
    {
        $totalPaid = $this->approvedPaymentTotal();
        $roomCount = Room::query()->count();
        $clientCount = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->count();
        $pendingMaintenance = MaintenanceRequest::query()
            ->where('status', 'pending')
            ->count();
        $newRoomsThisWeek = Room::query()
            ->where('created_at', '>=', now()->subWeek())
            ->count();
        $newClientsThisWeek = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        return [
            [
                'key' => 'revenue',
                'label' => 'Total Revenue',
                'value' => $this->formatMoney($totalPaid),
                'change' => $this->revenueChangeLabel(),
                'detail' => 'Approved payments across sale and rent portfolios total '.$this->formatMoney($totalPaid).'.',
                'icon' => 'pi pi-wallet',
            ],
            [
                'key' => 'properties',
                'label' => 'Properties',
                'value' => (string) $roomCount,
                'change' => $newRoomsThisWeek > 0 ? "{$newRoomsThisWeek} new this week" : 'No new listings this week',
                'detail' => "{$roomCount} luxury units are actively managed across Rosewood Royale towers.",
                'icon' => 'pi pi-building',
            ],
            [
                'key' => 'clients',
                'label' => 'Clients',
                'value' => (string) $clientCount,
                'change' => $newClientsThisWeek > 0 ? "+{$newClientsThisWeek} this week" : 'No new clients this week',
                'detail' => "{$clientCount} resident and owner profiles are registered in the customer portal.",
                'icon' => 'pi pi-users',
            ],
            [
                'key' => 'inquiries',
                'label' => 'Open Requests',
                'value' => (string) $pendingMaintenance,
                'change' => $pendingMaintenance > 0 ? "{$pendingMaintenance} awaiting action" : 'All caught up',
                'detail' => "{$pendingMaintenance} maintenance requests are currently pending review.",
                'icon' => 'pi pi-inbox',
            ],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function revenueSummary(): array
    {
        $totalPaid = $this->approvedPaymentTotal();
        $collectedThisMonth = $this->approvedPaymentTotalForMonth(now());
        $collectedLastMonth = $this->approvedPaymentTotalForMonth(now()->subMonth());
        $growthPercent = $collectedLastMonth > 0
            ? round((($collectedThisMonth - $collectedLastMonth) / $collectedLastMonth) * 100, 1)
            : ($collectedThisMonth > 0 ? 100.0 : 0.0);

        return [
            'total_paid' => $totalPaid,
            'outstanding' => $this->outstandingAmount(),
            'collected_this_month' => $collectedThisMonth,
            'growth_percent' => $growthPercent,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revenueChart(): array
    {
        $start = now()->subMonths(11)->startOfMonth();
        $points = [];

        for ($index = 0; $index < 12; $index++) {
            $monthStart = $start->copy()->addMonths($index);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $amount = (float) Payment::query()
                ->whereIn('status', ['approved', 'completed'])
                ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount');

            $points[] = [
                'month' => $monthStart->format('M'),
                'amount' => $amount,
            ];
        }

        return $points;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function propertyStats(): array
    {
        $counts = Room::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $definitions = [
            ['key' => 'available', 'label' => 'Available', 'color' => '#7a3149'],
            ['key' => 'reserved', 'label' => 'Reserved', 'color' => '#552032'],
            ['key' => 'occupied', 'label' => 'Occupied', 'color' => '#9b4d66'],
            ['key' => 'sold', 'label' => 'Sold', 'color' => '#d6b8c1'],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'color' => '#8b6b74'],
        ];

        return array_map(
            fn (array $definition) => [
                ...$definition,
                'value' => (int) ($counts[$definition['key']] ?? 0),
            ],
            $definitions,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceStats(): array
    {
        $counts = Invoice::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $definitions = [
            ['key' => 'draft', 'label' => 'Draft', 'icon' => 'pi pi-file', 'color' => '#8b6b74'],
            ['key' => 'issued', 'label' => 'Issued', 'icon' => 'pi pi-send', 'color' => '#552032'],
            ['key' => 'partial', 'label' => 'Partial', 'icon' => 'pi pi-minus-circle', 'color' => '#9b4d66'],
            ['key' => 'paid', 'label' => 'Paid', 'icon' => 'pi pi-check', 'color' => '#7a3149'],
            ['key' => 'overdue', 'label' => 'Overdue', 'icon' => 'pi pi-exclamation-triangle', 'color' => '#b42318'],
        ];

        return array_map(
            fn (array $definition) => [
                ...$definition,
                'value' => (int) ($counts[$definition['key']] ?? 0),
            ],
            $definitions,
        );
    }

    private function approvedPaymentTotal(): float
    {
        return (float) Payment::query()
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');
    }

    private function approvedPaymentTotalForMonth(Carbon $month): float
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        return (float) Payment::query()
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
    }

    private function outstandingAmount(): float
    {
        return (float) Invoice::query()
            ->withSum(['payments as paid_total' => fn ($query) => $query->whereIn('status', ['approved', 'completed'])], 'amount')
            ->get()
            ->sum(fn (Invoice $invoice) => max((float) $invoice->total_amount - (float) ($invoice->paid_total ?? 0), 0));
    }

    private function revenueChangeLabel(): string
    {
        $current = $this->approvedPaymentTotalForMonth(now());
        $previous = $this->approvedPaymentTotalForMonth(now()->subMonth());

        if ($previous <= 0) {
            return $current > 0 ? '+100% vs last month' : '0% vs last month';
        }

        $change = round((($current - $previous) / $previous) * 100, 1);
        $prefix = $change >= 0 ? '+' : '';

        return "{$prefix}{$change}% vs last month";
    }

    private function formatMoney(float $amount): string
    {
        return 'MMK '.number_format($amount, 0);
    }
}
