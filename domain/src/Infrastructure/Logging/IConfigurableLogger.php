<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Logging;

interface IConfigurableLogger extends ILogger
{
    public LogLevels $minLevel { get; set; }
    public string $format { get; set; }
}
