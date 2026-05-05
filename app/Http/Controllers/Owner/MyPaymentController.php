<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Payment::query()
                ->whereHas('invoice', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with(['invoice.property', 'receipt'])
                ->latest('payment_date')
                ->paginate(15)
        );
    }

    public function receipt(Request $request, Receipt $receipt): JsonResponse
    {
        abort_unless((int) $receipt->invoice->user_id === (int) $request->user()->id, 403);

        return response()->json($receipt->load(['payment', 'invoice.property']));
    }
}
