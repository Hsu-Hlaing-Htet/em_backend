<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AskRentQuestionRequest;
use App\Services\AiAssistantProxyService;
use App\Services\CustomerRentAssistantContextService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiRentAssistantController extends Controller
{
    public function ask(
        AskRentQuestionRequest $request,
        AiAssistantProxyService $aiAssistantProxyService,
        CustomerRentAssistantContextService $customerRentAssistantContextService,
    ): JsonResponse {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json([
                'message' => 'Missing bearer token.',
            ], 401);
        }

        // Load customer data in-process so FastAPI does not call Laravel back
        // (avoids deadlock on single-worker `php artisan serve`).
        $profile = $customerRentAssistantContextService->build($request->user(), $request);

        try {
            $result = $aiAssistantProxyService->askRent(
                [
                    'question' => $request->validated('question'),
                    'profile' => $profile,
                ],
                $token,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 502);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
