<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConcurrentConflictException;
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
        $maintenanceRequest->load(['room.building', 'user.profile', 'creator', 'approver', 'maintenanceCategory']);

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
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Maintenance request accepted successfully.',
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
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
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
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Maintenance request rejected successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    /**
     * Cancel an in-progress request (Admin UI cancel). Uses the same terminal
     * rejection path with an optional cancellation_reason alias.
     */
    public function cancel(
        Request $request,
        MaintenanceRequest $maintenanceRequest,
        MaintenanceRequestService $maintenanceRequestService,
    ): JsonResponse {
        $reason = trim((string) (
            $request->input('cancellation_reason')
            ?? $request->input('rejection_reason')
            ?? ''
        ));

        if ($reason === '') {
            return response()->json([
                'message' => 'Cancellation reason is required.',
                'data' => [
                    'cancellation_reason' => ['Cancellation reason is required.'],
                ],
            ], 422);
        }

        try {
            $maintenanceRequest = $maintenanceRequestService->reject($maintenanceRequest, $reason);
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Maintenance request cancelled successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }
}
