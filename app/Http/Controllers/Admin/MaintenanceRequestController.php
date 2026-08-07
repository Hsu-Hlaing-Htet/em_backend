<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteMaintenanceRequestRequest;
use App\Http\Requests\Admin\RejectMaintenanceRequestRequest;
use App\Http\Requests\Admin\StoreMaintenanceRequestRequest;
use App\Http\Requests\Admin\UpdateMaintenanceRequestRequest;
use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request, MaintenanceRequestService $maintenanceRequestService): JsonResponse
    {
        $paginator = $maintenanceRequestService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => MaintenanceRequestResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        StoreMaintenanceRequestRequest $request,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        $maintenanceRequest = $maintenanceRequestService->create($request->validated());

        return response()->json([
            'message' => 'Maintenance request created successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ], 201);
    }

    public function show(MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $maintenanceRequest->load(['room.building', 'user.profile', 'creator', 'approver']);

        return response()->json([
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    public function update(
        UpdateMaintenanceRequestRequest $request,
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        try {
            $maintenanceRequest = $maintenanceRequestService->update($maintenanceRequest, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request updated successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    public function destroy(
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        try {
            $maintenanceRequestService->delete($maintenanceRequest);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request deleted successfully.',
        ]);
    }

    public function start(
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        try {
            $maintenanceRequest = $maintenanceRequestService->start($maintenanceRequest);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request started successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    public function complete(
        CompleteMaintenanceRequestRequest $request,
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        try {
            $maintenanceRequest = $maintenanceRequestService->complete(
                $maintenanceRequest,
                $request->validated('resolution_note'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request completed successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    public function reject(
        RejectMaintenanceRequestRequest $request,
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        try {
            $maintenanceRequest = $maintenanceRequestService->reject(
                $maintenanceRequest,
                $request->validated('rejection_reason'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request rejected successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }
}
