<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure;

use Throwable;
use Vwork\Shared\Exception\VworkException;

/**
 * Use this class to throw exceptions related to infrastructure
 *
 * This enables callers to capture only infrastructure related exceptions
 * in one sweep
 *
 * ```
 * catch (InfrastructureException $err) {
 *     // handle your shit
 * }
 * ```
 *
 * @author Senira <senirahan@gmail.com>
 */
final class InfrastructureException extends VworkException
{
    /**
     * @param string $message - error message
     * @param class-string $class - infrastructure throwing the exception
     *
     * ```php
     * catch (RedisException $err) {
     *     throw new InfrastructureException(
     *          "Failed to store key-value pair <$key, $value>",
     *          ValkeyCache::class,
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
