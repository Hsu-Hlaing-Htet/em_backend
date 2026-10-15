<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
    }

    /**
     * Hard stop if Feature/Unit tests are not bound to the dedicated local test DB.
     * Prevents RefreshDatabase / migrations from touching development or production data.
     */
    protected function assertSafeTestingDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException(
                'Integration tests aborted: APP_ENV must be testing (got '.app()->environment().').'
            );
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $host = (string) config("database.connections.{$connection}.host");

        $allowedHosts = ['127.0.0.1', 'localhost', '::1'];
        $blockedNameFragments = [
            'railway',
            'render',
            'supabase',
            'planetscale',
            'aws',
            'azure',
            'production',
            'prod',
        ];

        if ($database !== 'rosewood_test') {
            throw new RuntimeException(
                "Integration tests aborted: DB_DATABASE must be rosewood_test (got {$database})."
            );
        }

        if ($connection !== 'mysql') {
            throw new RuntimeException(
                "Integration tests aborted: DB_CONNECTION must be mysql (got {$connection})."
            );
        }

        if (! in_array($host, $allowedHosts, true)) {
            throw new RuntimeException(
                "Integration tests aborted: DB_HOST must be local (got {$host})."
            );
        }

        $haystack = strtolower($database.' '.$host);
        foreach ($blockedNameFragments as $fragment) {
            if (str_contains($haystack, $fragment)) {
                throw new RuntimeException(
                    "Integration tests aborted: refused non-local/production-looking database target ({$fragment})."
                );
            }
        }
    }
}
