<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLateFeeRequest;
use App\Http\Requests\Admin\UpdateLateFeeRequest;
use App\Http\Resources\Admin\LateFeeResource;
use App\Models\LateFee;
use App\Services\LateFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LateFeeController extends Controller
{
    public function index(Request $request, LateFeeService $lateFeeService): JsonResponse
    {
        $this->authorize('viewAny', LateFee::class);

        $paginator = $lateFeeService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => LateFeeResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreLateFeeRequest $request, LateFeeService $lateFeeService): JsonResponse
    {
        $this->authorize('create', LateFee::class);

        $lateFee = $lateFeeService->create($request->validated());

        return response()->json([
            'message' => 'Late fee created successfully.',
            'data' => new LateFeeResource($lateFee),
        ], 201);
    }

    public function show(LateFee $lateFee): JsonResponse
    {
        $this->authorize('view', $lateFee);

        return response()->json([
            'data' => new LateFeeResource($lateFee),
        ]);
    }

    public function update(
        UpdateLateFeeRequest $request,
        LateFee $lateFee,
        LateFeeService $lateFeeService,
    ): JsonResponse {
        $this->authorize('update', $lateFee);

        $lateFee = $lateFeeService->update($lateFee, $request->validated());

        return response()->json([
            'message' => 'Late fee updated successfully.',
            'data' => new LateFeeResource($lateFee),
        ]);
    }

    public function destroy(LateFee $lateFee, LateFeeService $lateFeeService): JsonResponse
    {
        $this->authorize('delete', $lateFee);

        $lateFeeService->delete($lateFee);

        return response()->json([
            'message' => 'Late fee deleted successfully.',
        ]);
    }
}
