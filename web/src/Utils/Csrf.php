<?php

use Vwork\Shared\Types\Cast;
use Vwork\Web\WebError;

/**
 * A CSRF token is just an HMAC of the session ID — no storage, no
 * lookups. Same session in, same token out, so verifying it is just
 * making a new one and comparing.
 */
final class Csrf
{
    public static function create(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, self::key());
    }

    public static function verify(string $id, string $token): bool
    {
        return hash_equals(self::create($id), $token);
    }

    private static function key(): string
    {
        static $key = getenv('CSRF_KEY');

        if ($key === false || $key === '') {
            throw new WebError('CSRF_KEY is not set');
        }

        return Cast::string($key);
    }
}
