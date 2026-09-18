<?php

declare(strict_types=1);

namespace Vwork\Web;

use Throwable;
use Vwork\Shared\Exception\VworkException;

/**
 * Use this class to throw exceptions related to web
 *
 * This enables callers to capture only web related exceptions
 * in one sweep
 *
 * ```
 * catch (WebException $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
final class WebException extends VworkException
{
    /**
     * @param string $message - error message
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
