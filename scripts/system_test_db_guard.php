<?php

/**
 * Shared safety guard for Playwright System Testing scripts.
 * Aborts unless the configured database is exactly rosewood_system_test on a local host.
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

    return [
        'connection' => $connection,
        'database' => $database,
        'host' => $host,
        'app_env' => $app->environment(),
    ];
}
