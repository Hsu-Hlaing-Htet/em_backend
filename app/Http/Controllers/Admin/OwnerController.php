<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OwnerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::where('role', User::ROLE_OWNER)
                ->withCount(['ownedProperties', 'invoices'])
                ->latest('id')
                ->paginate(15)
        );
    }
}
