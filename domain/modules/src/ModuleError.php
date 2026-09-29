<?php

declare(strict_types=1);

namespace Vwork\Domain\Modules;

use Throwable;
use Vwork\Shared\Exception\VworkError;

/**
 * Use this class to throw Errors related to modules
 *
 * This enables callers to capture only modules related Errors
 * in one sweep
 *
 * ```
 * catch (ModuleError $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
class ModuleError extends VworkError
{
    /**
     * @param string $message - error message
     * @param class-string $class - modules throwing the Error
     *
     * ```php
     * catch (InfrastructureException $err) {
     *     throw new ModuleError();
     * }
     * ```
     */
    public function __construct(string $message, string $class, ?Throwable $previous = null)
    {
        parent::__construct("($class) $message", $previous);
    }
}
