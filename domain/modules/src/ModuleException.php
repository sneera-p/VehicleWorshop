<?php

declare(strict_types=1);

namespace Vwork\Domain\Modules;

use Throwable;
use Vwork\Shared\Exception\VworkException;

/**
 * Use this class to throw Exceptions related to modules
 *
 * This enables callers to capture only modules related Exceptions
 * in one sweep
 *
 * ```
 * catch (ModuleException $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
class ModuleException extends VworkException
{
    /**
     * @param string $message - error message
     * @param class-string $class - modules throwing the Exception
     *
     * ```php
     * catch (InfrastructureException $err) {
     *     throw new ModuleException();
     * }
     * ```
     */
    public function __construct(string $message, string $class, ?Throwable $previous = null)
    {
        parent::__construct("($class) $message", $previous);
    }
}
