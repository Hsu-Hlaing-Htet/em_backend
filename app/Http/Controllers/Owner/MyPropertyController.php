<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyPropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Property::query()
                ->where('owner_user_id', $request->user()->id)
                ->latest('id')
                ->get()
        );
    }
}
