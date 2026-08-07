<?php

namespace App\Support;

use App\Exceptions\ConcurrentConflictException;
use InvalidArgumentException;
use Throwable;

final class WorkflowResponse
{
    public static function statusFor(Throwable $exception): int
    {
        if ($exception instanceof ConcurrentConflictException) {
            return 409;
        }

        if ($exception instanceof InvalidArgumentException) {
            return 422;
        }

        return 500;
    }
}
