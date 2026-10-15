<?php

/**
 * Boot Laravel with forced System Testing database settings.
 * Does not permanently change .env.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

putenv('APP_ENV=local');
$_ENV['APP_ENV'] = 'local';
$_SERVER['APP_ENV'] = 'local';

putenv('DB_CONNECTION=mysql');
$_ENV['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_CONNECTION'] = 'mysql';

putenv('DB_HOST=127.0.0.1');
$_ENV['DB_HOST'] = '127.0.0.1';
$_SERVER['DB_HOST'] = '127.0.0.1';

putenv('DB_PORT=3306');
$_ENV['DB_PORT'] = '3306';
$_SERVER['DB_PORT'] = '3306';

putenv('DB_DATABASE=rosewood_system_test');
$_ENV['DB_DATABASE'] = 'rosewood_system_test';
$_SERVER['DB_DATABASE'] = 'rosewood_system_test';

putenv('DB_USERNAME=root');
$_ENV['DB_USERNAME'] = 'root';
$_SERVER['DB_USERNAME'] = 'root';

putenv('DB_PASSWORD=');
$_ENV['DB_PASSWORD'] = '';
$_SERVER['DB_PASSWORD'] = '';

putenv('MAIL_MAILER=array');
$_ENV['MAIL_MAILER'] = 'array';
$_SERVER['MAIL_MAILER'] = 'array';

putenv('QUEUE_CONNECTION=sync');
$_ENV['QUEUE_CONNECTION'] = 'sync';
$_SERVER['QUEUE_CONNECTION'] = 'sync';

putenv('CACHE_STORE=array');
$_ENV['CACHE_STORE'] = 'array';
$_SERVER['CACHE_STORE'] = 'array';

putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = 'array';
$_SERVER['SESSION_DRIVER'] = 'array';

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Force config after bootstrap in case .env overwrote putenv for some keys.
config([
    'database.default' => 'mysql',
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.database' => 'rosewood_system_test',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
    'mail.default' => 'array',
]);

require __DIR__.'/system_test_db_guard.php';

return $app;
