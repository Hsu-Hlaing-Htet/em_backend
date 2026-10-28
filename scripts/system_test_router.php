<?php

/**
 * PHP built-in server router for Playwright System Testing.
 * Uses verified runner settings and isolated public files for real browser requests.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$runtime = getenv('PW_SYSTEM_TEST_RUNTIME');
$publicRoot = $runtime ? $runtime.'/public' : $root.'/public';
$storageRoot = $runtime ? $runtime.'/storage' : $root.'/storage';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$segments = explode('/', str_replace('\\', '/', $uri));
if (str_contains($uri, "\0") || in_array('..', $segments, true)) {
    http_response_code(404);
    return;
}

if ($uri !== '/' && $uri !== '/index.php') {
    $public = realpath($publicRoot);
    $uploads = realpath($storageRoot.'/app/public');
    $file = realpath($publicRoot.'/'.ltrim($uri, '/'));
    if ($file !== false && is_file($file)) {
        $allowed = ($public !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR))
            || ($uploads !== false && str_starts_with($file, $uploads.DIRECTORY_SEPARATOR));
        if (! $allowed || preg_match('/\.(?:php\d*|phtml|phar)$/i', $file)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: '.((new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream'));
        readfile($file);
        return;
    }
}

if ($runtime) {
    define('LARAVEL_START', microtime(true));
    $app = require __DIR__.'/system_test_bootstrap.php';
    $app->handleRequest(\Illuminate\Http\Request::capture());
} else {
    // Retain standalone static-router fixtures; real test servers are guarded at startup.
    require $root.'/public/index.php';
}
