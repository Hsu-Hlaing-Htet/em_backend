<?php

namespace App\Support;

/**
 * Builds browser-reachable public disk URLs.
 *
 * Prefer the current HTTP request host so SPA clients that call
 * 127.0.0.1:8000 (or another local origin) do not receive APP_URL hosts
 * such as backend.test that may be unreachable or blocked.
 */
final class PublicStorageUrl
{
    public static function for(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $relative = 'storage/'.ltrim(str_replace('\\', '/', $path), '/');

        $request = request();

        if ($request !== null) {
            return $request->getSchemeAndHttpHost().'/'.$relative;
        }

        return rtrim((string) config('app.url'), '/').'/'.$relative;
    }
}
