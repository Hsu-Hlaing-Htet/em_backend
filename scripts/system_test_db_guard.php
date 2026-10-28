<?php

/**
 * Shared safety guard for Playwright System Testing scripts.
 * Requires rosewood_system_test on the verified disposable MySQL instance and isolated storage.
 */

declare(strict_types=1);

function system_test_assert_safe_database(Illuminate\Contracts\Foundation\Application $app): array
{
    $connection = (string) config('database.default');
    $database = (string) config("database.connections.{$connection}.database");
    $host = (string) config("database.connections.{$connection}.host");

    $allowedHosts = ['127.0.0.1', 'localhost', '::1'];
    $blockedFragments = ['railway', 'render', 'supabase', 'planetscale', 'aws', 'azure', 'production', 'prod'];

    if ($database !== 'rosewood_system_test') {
        throw new RuntimeException(
            "System test aborted: DB_DATABASE must be rosewood_system_test (got {$database})."
        );
    }

    if ($connection !== 'mysql') {
        throw new RuntimeException(
            "System test aborted: DB_CONNECTION must be mysql (got {$connection})."
        );
    }

    if (! in_array($host, $allowedHosts, true)) {
        throw new RuntimeException(
            "System test aborted: DB_HOST must be local (got {$host})."
        );
    }

    $haystack = strtolower($database.' '.$host);
    foreach ($blockedFragments as $fragment) {
        if (str_contains($haystack, $fragment)) {
            throw new RuntimeException(
                "System test aborted: refused non-local target ({$fragment})."
            );
        }
    }

    // Extra hard stop: never allow the known development / integration DB names.
    $forbidden = ['rosewood_royale', 'rosewood_test', 'rosewood'];
    if (in_array($database, $forbidden, true)) {
        throw new RuntimeException(
            "System test aborted: refused forbidden database name ({$database})."
        );
    }

    $runtime = realpath((string) getenv('PW_SYSTEM_TEST_RUNTIME'));
    $expectedData = realpath((string) getenv('PW_SYSTEM_TEST_MYSQL_DATA'));
    $port = (int) config("database.connections.{$connection}.port");
    if ($runtime === false || $expectedData !== $runtime.'/mysql-data'
        || $port <= 0 || $port === 3306
        || (string) config("database.connections.{$connection}.unix_socket") !== ''
        || $app->storagePath() !== $runtime.'/storage'
        || $app->publicPath() !== $runtime.'/public'
        || config('mail.default') !== 'array') {
        throw new RuntimeException('System test database/storage isolation is missing or unsafe.');
    }

    // Verify the connected server, not merely the configured database name.
    $identity = $app['db']->connection()->selectOne('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir');
    if ($identity->db !== 'rosewood_system_test' || (int) $identity->port !== $port
        || realpath($identity->datadir) !== $expectedData) {
        throw new RuntimeException('Connected MySQL identity does not match the disposable system-test runtime.');
    }

    return [
        'connection' => $connection,
        'database' => $database,
        'host' => $host,
        'port' => $port,
        'datadir' => $expectedData,
        'app_env' => $app->environment(),
    ];
}
