<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Tenant::query()->withCount(['contracts', 'invoices'])->latest('id')->paginate(15)
        );
    }
}
