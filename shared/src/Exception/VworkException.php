<?php

declare(strict_types=1);

namespace Vwork\Shared\Exception;

use Exception;
use Throwable;

/**
 * An expected, recoverable failure arising from legitimate runtime
 * conditions — not a bug. A job that's already completed, a validation
 * rule that failed, a webhook signature that didn't match, a record that
 * doesn't exist. Correct code throws these regularly.
 */
abstract class VworkException extends Exception
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
