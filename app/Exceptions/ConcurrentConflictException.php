<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a write conflicts with an already-processed or concurrently claimed record.
 */
class ConcurrentConflictException extends RuntimeException
{
}
