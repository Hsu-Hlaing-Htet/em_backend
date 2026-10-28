<?php

/** Boot the browser-test application only inside the verified disposable runtime. */
declare(strict_types=1);

$root = dirname(__DIR__);
$runtime = realpath((string) getenv('PW_SYSTEM_TEST_RUNTIME'));
$allowedRoot = realpath($root.'/.test-verification/runs');
if ($runtime === false || $allowedRoot === false
    || ! str_starts_with($runtime, $allowedRoot.DIRECTORY_SEPARATOR)
    || realpath((string) getenv('LARAVEL_STORAGE_PATH')) !== $runtime.'/storage'
    || (string) getenv('APP_CONFIG_CACHE') !== $runtime.'/cache/config.php') {
    throw new RuntimeException('System tests require the disposable runner; use npm run test:system in frontend.');
}

// External process settings override .env, including URL/socket aliases.
foreach (['APP_ENV', 'APP_KEY', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE',
    'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DB_SOCKET', 'MAIL_MAILER', 'QUEUE_CONNECTION',
    'CACHE_STORE', 'SESSION_DRIVER', 'LARAVEL_STORAGE_PATH', 'APP_CONFIG_CACHE',
    'APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'VIEW_COMPILED_PATH'] as $name) {
    $value = getenv($name);
    if ($value !== false) {
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useStoragePath($runtime.'/storage');
$app->usePublicPath($runtime.'/public');
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

require_once __DIR__.'/system_test_db_guard.php';
system_test_assert_safe_database($app);

return $app;
