<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'totals' => [
                'properties' => Property::count(),
                'owners' => User::where('role', User::ROLE_OWNER)->count(),
                'tenants' => Tenant::count(),
                'invoices' => Invoice::count(),
                'payments' => Payment::count(),
            ],
            'property_status' => [
                'available' => Property::where('status', Property::STATUS_AVAILABLE)->count(),
                'reserved' => Property::where('status', Property::STATUS_RESERVED)->count(),
                'occupied' => Property::where('status', Property::STATUS_OCCUPIED)->count(),
                'sold' => Property::where('status', Property::STATUS_SOLD)->count(),
            ],
            'invoice_status' => [
                'unpaid' => Invoice::where('status', Invoice::STATUS_UNPAID)->count(),
                'partial' => Invoice::where('status', Invoice::STATUS_PARTIAL)->count(),
                'paid' => Invoice::where('status', Invoice::STATUS_PAID)->count(),
                'overdue' => Invoice::where('status', Invoice::STATUS_OVERDUE)->count(),
            ],
            'revenue' => [
                'total_paid' => (float) Payment::sum('amount'),
                'outstanding' => (float) Invoice::sum('total_amount') - (float) Invoice::sum('paid_amount'),
            ],
        ]);
    }
}
