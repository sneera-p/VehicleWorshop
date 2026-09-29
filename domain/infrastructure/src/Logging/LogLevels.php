<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Logging;

/**
 * @author Senira <senirahan@gmail.com>
 */
enum LogLevels: int
{
    case Debug = 0;
    case Info = 1;
    case Warn = 2;
    case Error = 3;
    case Critical = 4;

    public function description(): string
    {
        return match ($this) {
            self::Debug => 'DEBUG',
            self::Info => 'INFO',
            self::Warn => 'WARNING',
            self::Error => 'ERROR',
            self::Critical => 'CRITICAL',
        };
    }
}
