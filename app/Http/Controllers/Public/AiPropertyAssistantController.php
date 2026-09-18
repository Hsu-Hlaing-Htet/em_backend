<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AskPropertyQuestionRequest;
use App\Http\Resources\Public\PublicPropertyResource;
use App\Services\AiAssistantProxyService;
use App\Services\PublicPropertyService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiPropertyAssistantController extends Controller
{
    public function ask(
        AskPropertyQuestionRequest $request,
        AiAssistantProxyService $aiAssistantProxyService,
        PublicPropertyService $publicPropertyService,
    ): JsonResponse {
        $validated = $request->validated();
        $purpose = $validated['purpose'] ?? 'sale';

        $payload = [
            'question' => $validated['question'],
            'purpose' => $purpose,
        ];

        if (array_key_exists('property_id', $validated) && $validated['property_id'] !== null) {
            $payload['property_id'] = (int) $validated['property_id'];
            $room = $publicPropertyService->find((int) $validated['property_id']);
            $payload['properties'] = [
                (new PublicPropertyResource($room))->resolve($request),
            ];
        } else {
            // Load listings in-process so FastAPI does not call Laravel back
            // (avoids deadlock on single-worker `php artisan serve`).
            $paginator = $publicPropertyService->paginate([
                'purpose' => $purpose,
                'per_page' => 24,
            ]);

            $payload['properties'] = PublicPropertyResource::collection($paginator->items())
                ->toArray($request);
        }

        // #region agent log
        try {
            @file_put_contents('/Users/hsuhtet/rosewood/.cursor/debug-cc4b96.log', json_encode([
                'sessionId' => 'cc4b96',
                'runId' => 'post-fix',
                'hypothesisId' => 'B',
                'location' => 'AiPropertyAssistantController.php:ask',
                'message' => 'Laravel preloaded properties for AI proxy',
                'data' => [
                    'purpose' => $purpose,
                    'propertyCount' => count($payload['properties']),
                    'question' => substr((string) $payload['question'], 0, 120),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ])."\n", FILE_APPEND);
        } catch (\Throwable) {
            // ignore debug log failures
        }
        // #endregion

        try {
            $result = $aiAssistantProxyService->askProperty($payload);
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
