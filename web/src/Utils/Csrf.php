<?php

namespace Vwork\Web\Utils;

use Vwork\Shared\Types\Cast;
use Vwork\Web\WebError;

/**
 * A CSRF token is just an HMAC of the session ID — no storage, no
 * lookups. Same session in, same token out, so verifying it is just
 * making a new one and comparing.
 */
final class Csrf
{
    /**
     * Reads Csrf key from ENV
     *
     * @throws WebError if ENV variable CSRF_KEY is not set
     */
    private static function key(): string
    {
        $key = getenv('CSRF_KEY');

        if ($key === false || $key === '') {
            throw new WebError('CSRF_KEY is not set');
        }

        return Cast::string($key);
    }

    public static function create(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, self::key());
    }

    public static function verify(string $sessionId, string $token): bool
    {
        return hash_equals(self::create($sessionId), $token);
    }
}
