<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Logging;

/**
 * doesn't do anything, acts as a placeholder if one does not need logging
 * @author Senira <senirahan@gmail.com>
 */
final class NullLogger implements ILogger
{
    public function connect(): void
    {
    }

    public LogLevels $minLevel {
        get => LogLevels::Debug;
    }

    public string $format {
        get => '';
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
    }
}
