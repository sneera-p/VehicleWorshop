<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

/**
 * What every header enum can do, whichever direction it travels in.
 * Lets HttpHeaderList work with RequestHeaders and ResponseHeaders alike.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface HttpHeader
{
    /**
     * Whether the value is a comma-separated list that is safe to split.
     * Everything else is one opaque value.
     */
    public function isList(): bool;

    /**
     * The header as it goes on the wire: "Name: value".
     */
    public function toLine(string $value): string;
}
