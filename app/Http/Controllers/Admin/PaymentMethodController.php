<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Resources\Admin\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request, PaymentMethodService $paymentMethodService): JsonResponse
    {
        $paginator = $paymentMethodService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => PaymentMethodResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StorePaymentMethodRequest $request, PaymentMethodService $paymentMethodService): JsonResponse
    {
        $paymentMethod = $paymentMethodService->create($request->validated());

        return response()->json([
            'message' => 'Payment method created successfully.',
            'data' => new PaymentMethodResource($paymentMethod),
        ], 201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return response()->json([
            'data' => new PaymentMethodResource($paymentMethod),
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService): JsonResponse
    {
        $paymentMethod = $paymentMethodService->update($paymentMethod, $request->validated());

        return response()->json([
            'message' => 'Payment method updated successfully.',
            'data' => new PaymentMethodResource($paymentMethod),
        ]);
    }

    public function destroy(PaymentMethod $paymentMethod, PaymentMethodService $paymentMethodService): JsonResponse
    {
        $paymentMethodService->delete($paymentMethod);

        return response()->json([
            'message' => 'Payment method deleted successfully.',
        ]);
    }
}
