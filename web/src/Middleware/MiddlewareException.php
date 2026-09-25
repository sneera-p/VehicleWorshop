<?php

declare(strict_types=1);

namespace Vwork\Web\Middleware;

use Throwable;
use Vwork\Web\WebException;

/**
 * Use this class to throw exceptions related to middleware
 *
 * This enables callers to capture only Middleware related exceptions
 * in one sweep
 *
 * ```
 * catch (MiddlewareException $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
final class MiddlewareException extends WebException
{
    /**
     * @param string $message - error message
     */
    public function __construct(string $message, IMiddleware $middleware, ?Throwable $previous = null)
    {
        $name = $middleware::class;
        parent::__construct("$name: $message", $previous);
    }
}
