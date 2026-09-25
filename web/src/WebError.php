<?php

declare(strict_types=1);

namespace Vwork\Web;

use Throwable;
use Vwork\Shared\Exception\VworkError;

/**
 * Use this class to throw errors related to web
 *
 * This enables callers to capture only web related errors
 * in one sweep
 *
 * ```
 * catch (WebError $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
class WebError extends VworkError
{
    /**
     * @param string $message - error message
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
