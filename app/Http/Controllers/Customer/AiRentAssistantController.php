<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AskRentQuestionRequest;
use App\Services\AiAssistantProxyService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiRentAssistantController extends Controller
{
    public function ask(
        AskRentQuestionRequest $request,
        AiAssistantProxyService $aiAssistantProxyService,
    ): JsonResponse {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json([
                'message' => 'Missing bearer token.',
            ], 401);
        }

        try {
            $result = $aiAssistantProxyService->askRent(
                ['question' => $request->validated('question')],
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
