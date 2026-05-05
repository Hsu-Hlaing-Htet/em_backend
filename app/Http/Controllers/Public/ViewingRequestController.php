<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\ViewingRequest\StoreViewingRequestRequest;
use App\Models\ViewingRequest;
use Illuminate\Http\JsonResponse;

class ViewingRequestController extends Controller
{
    public function store(StoreViewingRequestRequest $request): JsonResponse
    {
        $viewingRequest = ViewingRequest::create($request->validated());

        return response()->json([
            'message' => 'Request submitted successfully. We will contact you soon.',
            'data' => $viewingRequest,
        ], 201);
    }
}
