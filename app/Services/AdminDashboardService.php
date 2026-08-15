<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\Utility;
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
            'revenue_collections' => $this->revenueCollections(),
            'receivable_aging' => $this->receivableAging(),
            'occupancy_by_building' => $this->occupancyByBuilding(),
            'upcoming_contracts' => $this->upcomingContracts(),
            'pending_approval_breakdown' => $this->pendingApprovalBreakdown(),
            'property_stats' => $this->propertyStats(),
            'invoice_stats' => $this->invoiceStats(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpiStats(): array
    {
        $revenueSeries = $this->revenueSparklineSeries();
        $occupancySeries = $this->occupancySparklineSeries();
        $outstandingSeries = $this->outstandingSparklineSeries();
        $approvalsSeries = $this->pendingApprovalsSparklineSeries();

        $totalRevenue = $this->approvedPaymentTotal();
        $occupancyRate = $this->currentOccupancyRate();
        $outstanding = $this->outstandingAmount();
        $pendingApprovals = $this->pendingApprovalsCount();

        return [
            [
                'key' => 'revenue',
                'label' => 'Revenue',
                'value' => $this->formatMoney($totalRevenue),
                'change' => $this->revenueChangeLabel(),
                'trend' => $this->trendFromChangePercent($this->revenueChangePercent()),
                'detail' => 'Approved payments across sale and rent portfolios total '.$this->formatMoney($totalRevenue).'.',
                'sparkline' => $revenueSeries,
                'sparkline_period' => '6m',
            ],
            [
                'key' => 'occupancy',
                'label' => 'Occupancy',
                'value' => $this->formatPercent($occupancyRate),
                'change' => $this->occupancyChangeLabel($occupancySeries),
                'trend' => $this->trendFromSeries($occupancySeries),
                'detail' => 'Current occupancy across managed rooms is '.$this->formatPercent($occupancyRate).'.',
                'sparkline' => $occupancySeries,
                'sparkline_period' => '6m',
            ],
            [
                'key' => 'outstanding',
                'label' => 'Outstanding Balance',
                'value' => $this->formatMoney($outstanding),
                'change' => $this->outstandingChangeLabel($outstandingSeries),
                'trend' => $this->invertTrend($this->trendFromSeries($outstandingSeries)),
                'detail' => 'Open invoice balances currently total '.$this->formatMoney($outstanding).'.',
                'sparkline' => $outstandingSeries,
                'sparkline_period' => '6m',
            ],
            [
                'key' => 'pending_approvals',
                'label' => 'Pending Approvals',
                'value' => (string) $pendingApprovals,
                'change' => $pendingApprovals > 0
                    ? "{$pendingApprovals} awaiting action"
                    : 'All caught up',
                'trend' => $pendingApprovals > 0 ? 'down' : 'up',
                'detail' => "{$pendingApprovals} items are waiting in Approvals queues.",
                'sparkline' => $approvalsSeries,
                'sparkline_period' => '7d',
                'to' => '/admin/approvals/sale-contracts',
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
     * @return array<string, mixed>
     */
    private function revenueCollections(): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $points = [];
        $totalBilled = 0.0;
        $totalCollected = 0.0;

        for ($index = 0; $index < 6; $index++) {
            $monthStart = $start->copy()->addMonths($index);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $billed = (float) Invoice::query()
                ->where('status', '!=', 'draft')
                ->where(function ($query) use ($monthStart, $monthEnd): void {
                    $query->whereBetween('issued_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->orWhere(function ($inner) use ($monthStart, $monthEnd): void {
                            $inner->whereNull('issued_date')
                                ->whereBetween('created_at', [$monthStart, $monthEnd]);
                        });
                })
                ->sum('total_amount');

            $collected = (float) Payment::query()
                ->where('status', Payment::STATUS_APPROVED)
                ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount');

            $totalBilled += $billed;
            $totalCollected += $collected;

            $points[] = [
                'month' => $monthStart->format('M'),
                'billed' => $billed,
                'collected' => $collected,
            ];
        }

        $collectionRate = $totalBilled > 0
            ? round(($totalCollected / $totalBilled) * 100)
            : 0;

        return [
            'collection_rate' => $collectionRate,
            'points' => $points,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receivableAging(): array
    {
        $buckets = [
            'current' => ['label' => 'Current', 'amount' => 0.0, 'highlight' => false],
            '1_30' => ['label' => '1–30 days', 'amount' => 0.0, 'highlight' => false],
            '31_60' => ['label' => '31–60 days', 'amount' => 0.0, 'highlight' => false],
            '60_plus' => ['label' => '60+ days', 'amount' => 0.0, 'highlight' => true],
        ];

        $today = now()->startOfDay();

        Invoice::query()
            ->where('status', '!=', 'draft')
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->withSum(['payments as paid_total' => fn ($query) => $query->where('status', Payment::STATUS_APPROVED)], 'amount')
            ->get()
            ->each(function (Invoice $invoice) use (&$buckets, $today): void {
                $balance = max(
                    (float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0) - (float) ($invoice->paid_total ?? 0),
                    0,
                );

                if ($balance <= 0) {
                    return;
                }

                $dueDate = $invoice->due_date?->copy()->startOfDay();

                if (! $dueDate || $dueDate->greaterThanOrEqualTo($today)) {
                    $buckets['current']['amount'] += $balance;

                    return;
                }

                $daysPastDue = $dueDate->diffInDays($today);

                if ($daysPastDue <= 30) {
                    $buckets['1_30']['amount'] += $balance;
                } elseif ($daysPastDue <= 60) {
                    $buckets['31_60']['amount'] += $balance;
                } else {
                    $buckets['60_plus']['amount'] += $balance;
                }
            });

        $total = array_sum(array_column($buckets, 'amount'));

        return array_map(
            fn (string $key, array $bucket) => [
                'key' => $key,
                'label' => $bucket['label'],
                'amount' => round($bucket['amount'], 2),
                'amount_label' => $this->formatMoney($bucket['amount']),
                'percent' => $total > 0 ? round(($bucket['amount'] / $total) * 100) : 0,
                'highlight' => $bucket['highlight'],
            ],
            array_keys($buckets),
            $buckets,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function occupancyByBuilding(): array
    {
        return Building::query()
            ->withCount([
                'rooms',
                'rooms as occupied_count' => fn ($query) => $query->where('status', 'occupied'),
            ])
            ->orderByDesc('rooms_count')
            ->orderBy('building_name')
            ->limit(5)
            ->get()
            ->map(function (Building $building) {
                $total = max((int) $building->rooms_count, 0);
                $occupied = min((int) $building->occupied_count, $total);
                $percent = $total > 0 ? round(($occupied / $total) * 100) : 0;

                return [
                    'key' => 'building_'.$building->id,
                    'label' => $building->building_name,
                    'occupied' => $occupied,
                    'total' => $total,
                    'percent' => $percent,
                    'ratio_label' => "{$occupied}/{$total}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingContracts(): array
    {
        $windowEnd = now()->addDays(60)->endOfDay();

        return Contract::query()
            ->with(['room.building'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', $windowEnd->toDateString())
            ->orderBy('end_date')
            ->limit(5)
            ->get()
            ->map(function (Contract $contract) {
                $buildingName = $contract->room?->building?->building_name ?? '—';
                $roomNumber = $contract->room?->room_number ?? '—';
                $endDate = $contract->end_date;
                $daysLeft = $endDate
                    ? max(now()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false), 0)
                    : 0;

                return [
                    'id' => $contract->id,
                    'number' => $contract->contract_number,
                    'property' => "{$buildingName} · {$roomNumber}",
                    'end_date' => $endDate?->format('d M Y') ?? '—',
                    'days_left' => $daysLeft,
                    'to' => $contract->type === 'sale'
                        ? "/admin/sale-contracts/{$contract->id}"
                        : "/admin/rent-contracts/{$contract->id}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingApprovalBreakdown(): array
    {
        $items = [
            [
                'key' => 'payments',
                'label' => 'Payments',
                'icon' => 'pi pi-wallet',
                'count' => Payment::query()->where('status', 'pending')->count(),
                'to' => '/admin/payments/approval',
            ],
            [
                'key' => 'invoices',
                'label' => 'Invoices',
                'icon' => 'pi pi-receipt',
                'count' => Invoice::query()->where('status', 'draft')->count(),
                'to' => '/admin/invoices/approval',
            ],
            [
                'key' => 'utilities',
                'label' => 'Utilities',
                'icon' => 'pi pi-bolt',
                'count' => Utility::query()->where('status', 'pending')->count(),
                'to' => '/admin/utilities/approval',
            ],
            [
                'key' => 'others',
                'label' => 'Others',
                'icon' => 'pi pi-ellipsis-h',
                'count' => $this->pendingSaleContractCount()
                    + $this->pendingRentContractCount()
                    + Receipt::query()->where('approval_status', Receipt::APPROVAL_PENDING)->count(),
                'to' => '/admin/approvals/sale-contracts',
            ],
        ];

        return [
            'total' => array_sum(array_column($items, 'count')),
            'items' => $items,
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
                ->where('status', Payment::STATUS_APPROVED)
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

    /**
     * Last six months of approved payment totals.
     *
     * @return list<float>
     */
    private function revenueSparklineSeries(): array
    {
        $series = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $series[] = $this->approvedPaymentTotalForMonth(now()->subMonths($offset));
        }

        return $series;
    }

    /**
     * Last six months of occupancy rate based on active rent contracts.
     *
     * @return list<float>
     */
    private function occupancySparklineSeries(): array
    {
        $totalRooms = max(Room::query()->count(), 1);
        $series = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $monthEnd = now()->subMonths($offset)->endOfMonth();
            $occupied = $this->activeRentRoomsAsOf($monthEnd);
            $series[] = round(($occupied / $totalRooms) * 100, 1);
        }

        return $series;
    }

    /**
     * Reconstructed outstanding balance at each of the last six month-ends.
     *
     * @return list<float>
     */
    private function outstandingSparklineSeries(): array
    {
        $series = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $monthEnd = now()->subMonths($offset)->endOfMonth();
            $series[] = $this->outstandingAmountAsOf($monthEnd);
        }

        return $series;
    }

    /**
     * New pending-approval items created on each of the last seven days.
     *
     * @return list<float>
     */
    private function pendingApprovalsSparklineSeries(): array
    {
        $series = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = now()->subDays($offset);
            $series[] = (float) $this->pendingApprovalsCreatedOn($day);
        }

        return $series;
    }

    private function pendingApprovalsCount(): int
    {
        return $this->pendingSaleContractCount()
            + $this->pendingRentContractCount()
            + Utility::query()->where('status', 'pending')->count()
            + Invoice::query()->where('status', 'draft')->count()
            + Payment::query()->where('status', 'pending')->count()
            + Receipt::query()->where('approval_status', Receipt::APPROVAL_PENDING)->count();
    }

    private function pendingApprovalsCreatedOn(Carbon $day): int
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        return Contract::query()
            ->where('type', 'sale')
            ->where('status', 'draft')
            ->whereBetween('created_at', [$start, $end])
            ->count()
            + Contract::query()
                ->where('type', 'rent')
                ->where('status', 'draft')
                ->whereBetween('created_at', [$start, $end])
                ->count()
            + Utility::query()
                ->where('status', 'pending')
                ->whereBetween('updated_at', [$start, $end])
                ->count()
            + Invoice::query()
                ->where('status', 'draft')
                ->whereBetween('created_at', [$start, $end])
                ->count()
            + Payment::query()
                ->where('status', 'pending')
                ->whereBetween('created_at', [$start, $end])
                ->count()
            + Receipt::query()
                ->where('approval_status', Receipt::APPROVAL_PENDING)
                ->whereBetween('created_at', [$start, $end])
                ->count();
    }

    private function pendingSaleContractCount(): int
    {
        return Contract::query()
            ->where('type', 'sale')
            ->where('status', 'draft')
            ->count();
    }

    private function pendingRentContractCount(): int
    {
        return Contract::query()
            ->where('type', 'rent')
            ->where('status', 'draft')
            ->count();
    }

    private function currentOccupancyRate(): float
    {
        $totalRooms = Room::query()->count();

        if ($totalRooms === 0) {
            return 0.0;
        }

        $occupied = Room::query()->where('status', 'occupied')->count();

        return round(($occupied / $totalRooms) * 100, 1);
    }

    private function activeRentRoomsAsOf(Carbon $asOf): int
    {
        return (int) Contract::query()
            ->where('type', 'rent')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $asOf->toDateString());
            })
            ->distinct('room_id')
            ->count('room_id');
    }

    private function outstandingAmountAsOf(Carbon $asOf): float
    {
        $invoiced = (float) Invoice::query()
            ->where(function ($query) use ($asOf): void {
                $query->whereDate('issued_date', '<=', $asOf->toDateString())
                    ->orWhere(function ($inner) use ($asOf): void {
                        $inner->whereNull('issued_date')
                            ->whereDate('created_at', '<=', $asOf->toDateString());
                    });
            })
            ->where('status', '!=', 'draft')
            ->get()
            ->sum(fn (Invoice $invoice) => (float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0));

        $paid = (float) Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereDate('payment_date', '<=', $asOf->toDateString())
            ->sum('amount');

        return max(round($invoiced - $paid, 2), 0);
    }

    private function approvedPaymentTotal(): float
    {
        return (float) Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->sum('amount');
    }

    private function approvedPaymentTotalForMonth(Carbon $month): float
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        return (float) Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
    }

    private function outstandingAmount(): float
    {
        return (float) Invoice::query()
            ->where('status', '!=', 'draft')
            ->withSum(['payments as paid_total' => fn ($query) => $query->where('status', Payment::STATUS_APPROVED)], 'amount')
            ->get()
            ->sum(function (Invoice $invoice) {
                $due = (float) $invoice->total_amount + (float) ($invoice->late_fee ?? 0);

                return max($due - (float) ($invoice->paid_total ?? 0), 0);
            });
    }

    private function revenueChangePercent(): float
    {
        $current = $this->approvedPaymentTotalForMonth(now());
        $previous = $this->approvedPaymentTotalForMonth(now()->subMonth());

        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function revenueChangeLabel(): string
    {
        $change = $this->revenueChangePercent();
        $prefix = $change > 0 ? '+' : '';

        return "{$prefix}{$change}% vs last month";
    }

    /**
     * @param  list<float>  $series
     */
    private function occupancyChangeLabel(array $series): string
    {
        if (count($series) < 2) {
            return '0% vs last month';
        }

        $current = (float) $series[count($series) - 1];
        $previous = (float) $series[count($series) - 2];
        $delta = round($current - $previous, 1);
        $prefix = $delta > 0 ? '+' : '';

        return "{$prefix}{$delta} pts vs last month";
    }

    /**
     * @param  list<float>  $series
     */
    private function outstandingChangeLabel(array $series): string
    {
        if (count($series) < 2) {
            return '0% vs last month';
        }

        $current = (float) $series[count($series) - 1];
        $previous = (float) $series[count($series) - 2];

        if ($previous <= 0) {
            return $current > 0 ? '+100% vs last month' : '0% vs last month';
        }

        $change = round((($current - $previous) / $previous) * 100, 1);
        $prefix = $change > 0 ? '+' : '';

        return "{$prefix}{$change}% vs last month";
    }

    /**
     * @param  list<float>  $series
     */
    private function trendFromSeries(array $series): string
    {
        if (count($series) < 2) {
            return 'neutral';
        }

        $current = (float) $series[count($series) - 1];
        $previous = (float) $series[count($series) - 2];

        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'neutral';
    }

    private function trendFromChangePercent(float $change): string
    {
        if ($change > 0) {
            return 'up';
        }

        if ($change < 0) {
            return 'down';
        }

        return 'neutral';
    }

    private function invertTrend(string $trend): string
    {
        return match ($trend) {
            'up' => 'down',
            'down' => 'up',
            default => 'neutral',
        };
    }

    private function formatMoney(float $amount): string
    {
        return 'MMK '.number_format($amount, 0);
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }
}
