<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBillingDocumentRequest;
use App\Http\Requests\Admin\StoreUtilityRequest;
use App\Http\Requests\Admin\UpdateUtilityRequest;
use App\Http\Resources\Admin\UtilityResource;
use App\Models\Utility;
use App\Services\UtilityDocumentService;
use App\Services\UtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class UtilityController extends Controller
{
    public function index(Request $request, UtilityService $utilityService): JsonResponse
    {
        $paginator = $utilityService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => UtilityResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreUtilityRequest $request, UtilityService $utilityService): JsonResponse
    {
        try {
            $utility = $utilityService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill created successfully.',
            'data' => new UtilityResource($utility),
        ], 201);
    }

    public function show(Utility $utility): JsonResponse
    {
        $utility->load(['room.building', 'items.utilityType', 'creator', 'approver']);

        return response()->json([
            'data' => new UtilityResource($utility),
        ]);
    }

    public function update(
        UpdateUtilityRequest $request,
        Utility $utility,
        UtilityService $utilityService,
    ): JsonResponse {
        try {
            $utility = $utilityService->update($utility, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill updated successfully.',
            'data' => new UtilityResource($utility),
        ]);
    }

    public function destroy(Utility $utility, UtilityService $utilityService): JsonResponse
    {
        try {
            $utilityService->delete($utility);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill deleted successfully.',
        ]);
    }

    public function submit(Utility $utility, UtilityService $utilityService): JsonResponse
    {
        try {
            $utility = $utilityService->submit($utility);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill submitted successfully.',
            'data' => new UtilityResource($utility),
        ]);
    }

    public function approve(Utility $utility, UtilityService $utilityService): JsonResponse
    {
        try {
            $utility = $utilityService->approve($utility);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill approved successfully.',
            'data' => new UtilityResource($utility),
        ]);
    }

    public function reject(Utility $utility, UtilityService $utilityService): JsonResponse
    {
        try {
            $utility = $utilityService->reject($utility);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill rejected successfully.',
            'data' => new UtilityResource($utility),
        ]);
    }

    public function downloadDocument(Utility $utility, UtilityDocumentService $utilityDocumentService): Response
    {
        return $utilityDocumentService->downloadResponse($utilityDocumentService->find($utility->id));
    }

    public function exportDocument(Utility $utility, UtilityDocumentService $utilityDocumentService): Response
    {
        return $utilityDocumentService->exportResponse($utilityDocumentService->find($utility->id));
    }

    public function sendDocumentEmail(
        SendBillingDocumentRequest $request,
        Utility $utility,
        UtilityDocumentService $utilityDocumentService,
    ): JsonResponse {
        try {
            $utilityDocumentService->sendEmail(
                $utilityDocumentService->find($utility->id),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Utility bill document sent successfully.',
        ]);
    }
}
