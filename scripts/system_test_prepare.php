<?php

declare(strict_types=1);

$app = require __DIR__.'/system_test_bootstrap.php';
$info = system_test_assert_safe_database($app);

fwrite(STDOUT, json_encode(['phase' => 'prepare', 'db' => $info], JSON_PRETTY_PRINT).PHP_EOL);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$migrate = $kernel->call('migrate:fresh', ['--force' => true]);
if ($migrate !== 0) {
    throw new RuntimeException('migrate:fresh failed for rosewood_system_test');
}

$seed = $kernel->call('db:seed', [
    '--class' => 'Database\\Seeders\\SystemTestSeeder',
    '--force' => true,
]);
if ($seed !== 0) {
    throw new RuntimeException('SystemTestSeeder failed');
}

fwrite(STDOUT, json_encode([
    'phase' => 'prepare_complete',
    'database' => 'rosewood_system_test',
    'mailer' => config('mail.default'),
], JSON_PRETTY_PRINT).PHP_EOL);
