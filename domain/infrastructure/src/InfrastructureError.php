<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure;

use Throwable;
use Vwork\Shared\Exception\VworkError;

/**
 * Use this class to throw Errors related to infrastructure
 *
 * This enables callers to capture only infrastructure related Errors
 * in one sweep
 *
 * ```
 * catch (InfrastructureError $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
final class InfrastructureError extends VworkError
{
    /**
     * @param string $message - error message
     * @param class-string $class - infrastructure throwing the Error
     *
     * ```php
     * catch (PDOException $err) {
     *     throw new InfrastructureError(
     *          "Failed to connect to database",
     *          PostgresDb::class,
     *          $err
     *      );
     * }
     * ```
     */
    public function __construct(string $message, string $class, ?Throwable $previous = null)
    {
        parent::__construct("($class) $message", $previous);
    }
}
