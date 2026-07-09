<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $paidTotal = (float) Payment::query()
            ->where('status', 'approved')
            ->sum('amount');

        $invoiceTotal = (float) Invoice::query()
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->sum('total_amount');

        return response()->json([
            'data' => [
                'totals' => [
                    'rooms' => Room::count(),
                    'residents' => User::query()->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))->count(),
                    'contracts' => Contract::count(),
                    'invoices' => Invoice::count(),
                    'payments' => Payment::count(),
                    'maintenance_requests' => MaintenanceRequest::count(),
                ],
                'room_status' => [
                    'available' => Room::where('status', 'available')->count(),
                    'reserved' => Room::where('status', 'reserved')->count(),
                    'occupied' => Room::where('status', 'occupied')->count(),
                    'sold' => Room::where('status', 'sold')->count(),
                    'maintenance' => Room::where('status', 'maintenance')->count(),
                ],
                'invoice_status' => [
                    'draft' => Invoice::where('status', 'draft')->count(),
                    'issued' => Invoice::where('status', 'issued')->count(),
                    'partial' => Invoice::where('status', 'partial')->count(),
                    'paid' => Invoice::where('status', 'paid')->count(),
                    'overdue' => Invoice::where('status', 'overdue')->count(),
                ],
                'revenue' => [
                    'total_paid' => $paidTotal,
                    'outstanding' => max(0, $invoiceTotal - $paidTotal),
                ],
            ],
        ]);
    }
}
