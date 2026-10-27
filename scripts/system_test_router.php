<?php

/**
 * PHP built-in server router for Playwright System Testing.
 * Forces rosewood_system_test before Laravel boots (artisan serve may keep .env DB).
 */

declare(strict_types=1);

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=rosewood_system_test');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');
putenv('MAIL_MAILER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:5174,http://127.0.0.1:5173,http://127.0.0.1:5174');
putenv('FRONTEND_URL=http://localhost:5174');

$_ENV['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_SERVER['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_SERVER['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'rosewood_system_test';
$_SERVER['DB_DATABASE'] = 'rosewood_system_test';
$_ENV['DB_USERNAME'] = 'root';
$_SERVER['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$_SERVER['DB_PASSWORD'] = '';
$_ENV['MAIL_MAILER'] = 'array';
$_SERVER['MAIL_MAILER'] = 'array';
$_ENV['CORS_ALLOWED_ORIGINS'] = 'http://localhost:5173,http://localhost:5174,http://127.0.0.1:5173,http://127.0.0.1:5174';
$_SERVER['CORS_ALLOWED_ORIGINS'] = $_ENV['CORS_ALLOWED_ORIGINS'];
$_ENV['FRONTEND_URL'] = 'http://localhost:5174';
$_SERVER['FRONTEND_URL'] = 'http://localhost:5174';

$root = dirname(__DIR__);
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$segments = explode('/', str_replace('\\', '/', $uri));
if (str_contains($uri, "\0") || in_array('..', $segments, true)) {
    http_response_code(404);
    return;
}

if ($uri !== '/' && $uri !== '/index.php') {
    $public = realpath($root.'/public');
    $uploads = realpath($root.'/storage/app/public');
    $file = realpath($root.'/public/'.ltrim($uri, '/'));
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

require $root.'/public/index.php';
