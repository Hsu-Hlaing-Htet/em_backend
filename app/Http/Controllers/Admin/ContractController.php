<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contract::query()->with(['property', 'owner', 'tenant']);

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->string('contract_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->latest('id')->paginate(15));
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json($contract->load(['property', 'owner', 'tenant', 'invoices']));
    }
}
