<?php

declare(strict_types=1);

namespace Vwork\Shared\Types;

use Vwork\Shared\Exception\VworkError;

/**
 * Narrows mixed values to concrete types, throwing when the value isn't
 * what the caller claims. For the boundaries where PHP hands back mixed
 * (superglobals, getenv, json_decode) and static analysis needs proof.
 */
final class Cast
{
    /**
     * @throws VworkError when $value isn't a string
     */
    public static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new VworkError('Expected string, got ' . get_debug_type($value));
        }
        return $value;
    }

    /**
     * @param array<mixed, mixed> $values
     * @return array<string, string>
     * @throws VworkError when any value isn't a string
     */
    public static function stringMap(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = self::string($value);
        }
        return $result;
    }
}
