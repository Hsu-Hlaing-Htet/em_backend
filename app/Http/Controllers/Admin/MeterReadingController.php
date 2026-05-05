<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeterReading\StoreMeterReadingRequest;
use App\Models\MeterReading;
use App\Services\MeterReadingService;
use Illuminate\Http\JsonResponse;

class MeterReadingController extends Controller
{
    public function __construct(private readonly MeterReadingService $meterReadingService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            MeterReading::query()->with(['property', 'contract'])->latest('reading_date')->paginate(15)
        );
    }

    public function store(StoreMeterReadingRequest $request): JsonResponse
    {
        $calculation = $this->meterReadingService->calculate(
            previous: (float) $request->input('previous_reading'),
            current: (float) $request->input('current_reading'),
            ratePerUnit: (float) $request->input('rate_per_unit')
        );

        $meterReading = MeterReading::create([
            ...$request->validated(),
            ...$calculation,
            'recorded_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Meter reading recorded successfully.',
            'data' => $meterReading,
        ], 201);
    }
}
