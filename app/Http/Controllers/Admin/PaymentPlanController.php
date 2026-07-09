<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentPlanRequest;
use App\Http\Requests\Admin\UpdatePaymentPlanRequest;
use App\Http\Resources\Admin\PaymentPlanResource;
use App\Models\PaymentPlan;
use App\Services\PaymentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentPlanController extends Controller
{
    public function index(Request $request, PaymentPlanService $paymentPlanService): JsonResponse
    {
        $paginator = $paymentPlanService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => PaymentPlanResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StorePaymentPlanRequest $request, PaymentPlanService $paymentPlanService): JsonResponse
    {
        $paymentPlan = $paymentPlanService->create($request->validated());

        return response()->json([
            'message' => 'Payment plan created successfully.',
            'data' => new PaymentPlanResource($paymentPlan),
        ], 201);
    }

    public function show(PaymentPlan $paymentPlan): JsonResponse
    {
        return response()->json([
            'data' => new PaymentPlanResource($paymentPlan),
        ]);
    }

    public function update(UpdatePaymentPlanRequest $request, PaymentPlan $paymentPlan, PaymentPlanService $paymentPlanService): JsonResponse
    {
        $paymentPlan = $paymentPlanService->update($paymentPlan, $request->validated());

        return response()->json([
            'message' => 'Payment plan updated successfully.',
            'data' => new PaymentPlanResource($paymentPlan),
        ]);
    }

    public function destroy(PaymentPlan $paymentPlan, PaymentPlanService $paymentPlanService): JsonResponse
    {
        $paymentPlanService->delete($paymentPlan);

        return response()->json([
            'message' => 'Payment plan deleted successfully.',
        ]);
    }
}
