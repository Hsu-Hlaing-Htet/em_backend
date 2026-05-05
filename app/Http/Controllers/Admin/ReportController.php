<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Property;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function monthlyPayments(): JsonResponse
    {
        $report = Payment::query()
            ->selectRaw("strftime('%Y-%m', payment_date) as month")
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json($report);
    }

    public function unpaidInvoices(): JsonResponse
    {
        return response()->json(
            Invoice::query()
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->with(['property'])
                ->latest('due_date')
                ->get()
        );
    }

    public function occupancy(): JsonResponse
    {
        return response()->json([
            'occupied' => Property::where('status', Property::STATUS_OCCUPIED)->count(),
            'available' => Property::where('status', Property::STATUS_AVAILABLE)->count(),
            'reserved' => Property::where('status', Property::STATUS_RESERVED)->count(),
            'sold' => Property::where('status', Property::STATUS_SOLD)->count(),
        ]);
    }

    public function utilityUsage(): JsonResponse
    {
        return response()->json(
            MeterReading::query()
                ->selectRaw('meter_type, SUM(usage) as total_usage, SUM(calculated_amount) as total_amount')
                ->groupBy('meter_type')
                ->get()
        );
    }
}
