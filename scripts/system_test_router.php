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
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = $root.'/public'.$uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require $root.'/public/index.php';
