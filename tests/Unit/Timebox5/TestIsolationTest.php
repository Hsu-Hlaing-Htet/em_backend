<?php

use App\Models\User;
use Tests\Support\TestDatabaseSafety;

test('unit tests reject accidental SQL instead of connecting to a real database', function (): void {
    expect(fn () => User::query()->exists())->toThrow(LogicException::class, 'Unit tests cannot execute SQL');
});

test('database safety rejects unsafe or unconfirmed targets before connecting', function (array $config, string $environment, bool $disposable): void {
    expect(fn () => TestDatabaseSafety::assertConfiguration($config, $environment, $disposable))
        ->toThrow(RuntimeException::class);
})->with([
    'development environment' => [['driver' => 'mysql', 'database' => 'rosewood_test', 'host' => '127.0.0.1'], 'local', true],
    'no disposable opt-in' => [['driver' => 'mysql', 'database' => 'rosewood_test', 'host' => '127.0.0.1'], 'testing', false],
    'development database' => [['driver' => 'mysql', 'database' => 'rosewood_royale', 'host' => '127.0.0.1'], 'testing', true],
    'production host' => [['driver' => 'mysql', 'database' => 'rosewood_test', 'host' => 'production.example.org'], 'testing', true],
    'SQLite file' => [['driver' => 'sqlite', 'database' => 'database/database.sqlite'], 'testing', true],
    'different driver' => [['driver' => 'pgsql', 'database' => 'rosewood_test', 'host' => '127.0.0.1'], 'testing', true],
]);

test('database safety permits a confirmed local test database or SQLite memory', function (array $config, bool $disposable): void {
    expect(fn () => TestDatabaseSafety::assertConfiguration($config, 'testing', $disposable))
        ->not->toThrow(RuntimeException::class);
})->with([
    'disposable MySQL' => [['driver' => 'mysql', 'database' => 'rosewood_test', 'host' => '127.0.0.1'], true],
    'in-memory SQLite' => [['driver' => 'sqlite', 'database' => ':memory:'], false],
]);
