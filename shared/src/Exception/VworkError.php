<?php

declare(strict_types=1);

namespace Vwork\Shared\Exception;

use Error;
use Throwable;

/**
 * A programmer or configuration mistake — never an expected runtime outcome.
 *
 * Thrown when something in vwork's own wiring is broken. If a VworkError
 * fires, the fix is always a code change — never a caller catching it and
 * handling the situation gracefully. Not meant to be caught anywhere except
 * a single top-level boundary, which logs it and returns a generic failure
 * response — the caller never sees the real message.
 *
 * Extends PHP's own \Error (not \Exception) deliberately: it puts VworkError
 * in the same category as TypeError/DivisionByZeroError — failures that
 * indicate broken code, not conditions a caller is expected to plan around.
 */
class VworkError extends Error
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
