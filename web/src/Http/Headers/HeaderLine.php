<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

/**
 * Shared toLine() for the header enums. Enums can't extend each other,
 * so behaviour they have in common lives here instead.
 *
 * @phpstan-require-implements HttpHeader
 * @phpstan-require-implements \BackedEnum
 *
 * @author Senira <senirahan@gmail.com>
 */
trait HeaderLine
{
    public function toLine(string $value): string
    {
        return "{$this->value}: {$value}";
    }
}
