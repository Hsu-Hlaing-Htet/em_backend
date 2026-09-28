<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistantProxyService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function askProperty(array $payload): array
    {
        return $this->post('/api/v1/property/ask', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function askRent(array $payload, string $bearerToken): array
    {
        return $this->post('/api/v1/rent/ask', $payload, $bearerToken);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, ?string $bearerToken = null): array
    {
        $request = Http::baseUrl(config('ai.base_url'))
            ->timeout(config('ai.timeout_seconds'))
            ->acceptJson()
            ->asJson()
            // Ignore process HTTP(S)_PROXY so local AI calls are not diverted.
            ->withOptions([
                'proxy' => [
                    'http' => null,
                    'https' => null,
                ],
            ]);

        $internalKey = config('ai.internal_key');
        if (is_string($internalKey) && $internalKey !== '') {
            $request = $request->withHeaders([
                'X-Rosewood-AI-Internal-Key' => $internalKey,
            ]);
        }

        if ($bearerToken !== null && $bearerToken !== '') {
            $request = $request->withToken($bearerToken);
        }

        try {
            $response = $request->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('AI service is unavailable.', 502, $exception);
        }

        if ($response->failed()) {
            $detail = $response->json('detail');
            $message = is_string($detail)
                ? $detail
                : (is_array($detail) ? ($detail['message'] ?? 'AI service request failed.') : 'AI service request failed.');

            throw new RuntimeException($message, $response->status() >= 500 ? 502 : $response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return $body;
    }
}
