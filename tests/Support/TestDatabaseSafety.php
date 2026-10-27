<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseSafety
{
    /** Validate resolved connection settings BEFORE PDO or RefreshDatabase runs. */
    public static function assertConfiguration(array $config, string $environment, bool $disposable): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('Tests require APP_ENV=testing.');
        }
        if (($config['driver'] ?? '') === 'sqlite' && ($config['database'] ?? '') === ':memory:') {
            return;
        }
        if (! $disposable) {
            throw new RuntimeException('Database tests require TEST_DATABASE_DISPOSABLE=1 after isolation is verified. Use scripts/verify_timebox_tests.py.');
        }
        if (($config['driver'] ?? '') !== 'mysql'
            || ($config['database'] ?? '') !== 'rosewood_test'
            || ! in_array($config['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('Tests may use only local MySQL rosewood_test or SQLite :memory:.');
        }
    }
}
