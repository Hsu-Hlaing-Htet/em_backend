<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\Connection;
use LogicException;

/** Supplies Eloquent's SQL grammar for casts, but never opens a database. */
final class NoDatabaseConnection extends Connection
{
    protected function run($query, $bindings, Closure $callback)
    {
        throw new LogicException('Unit tests cannot execute SQL. Use an Integration test for database behaviour.');
    }

    public function getPdo()
    {
        throw new LogicException('Unit tests cannot open a database connection.');
    }
}
