<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Throwable;
use Vwork\Web\WebError;

/**
 * Use this class to throw exceptions related to controllers
 *
 * This enables callers to capture only Controller related exceptions
 * in one sweep
 *
 * ```
 * catch (ControllerError $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
final class ControllerError extends WebError
{
    /**
     * @param string $message - error message
     */
    public function __construct(string $message, IController $controller, ?Throwable $previous = null)
    {
        $name = $controller::class;
        parent::__construct("$name: $message", $previous);
    }
}
