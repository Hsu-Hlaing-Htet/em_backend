<?php

declare(strict_types=1);

$app = require __DIR__.'/system_test_bootstrap.php';
$info = system_test_assert_safe_database($app);

fwrite(STDOUT, json_encode(['phase' => 'cleanup', 'db' => $info], JSON_PRETTY_PRINT).PHP_EOL);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Wipe business data by migrating fresh without seeding — dedicated system DB only.
$status = $kernel->call('migrate:fresh', ['--force' => true]);
if ($status !== 0) {
    throw new RuntimeException('cleanup migrate:fresh failed for rosewood_system_test');
}

fwrite(STDOUT, json_encode([
    'phase' => 'cleanup_complete',
    'database' => 'rosewood_system_test',
    'seeded' => false,
], JSON_PRETTY_PRINT).PHP_EOL);
