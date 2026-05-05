<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;

        return response()->json([
            'totals' => [
                'properties' => Property::where('owner_user_id', $ownerId)->count(),
                'invoices' => Invoice::where('user_id', $ownerId)->count(),
                'payments' => Payment::whereHas('invoice', fn ($q) => $q->where('user_id', $ownerId))->count(),
            ],
            'invoice_status' => [
                'unpaid' => Invoice::where('user_id', $ownerId)->where('status', Invoice::STATUS_UNPAID)->count(),
                'partial' => Invoice::where('user_id', $ownerId)->where('status', Invoice::STATUS_PARTIAL)->count(),
                'paid' => Invoice::where('user_id', $ownerId)->where('status', Invoice::STATUS_PAID)->count(),
            ],
        ]);
    }
}
