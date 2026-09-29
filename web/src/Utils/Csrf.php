<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Vwork\Shared\Types\Cast;
use Vwork\Web\WebError;

/**
 * A CSRF token is just an HMAC of the session ID — no storage, no
 * lookups. Same session in, same token out, so verifying it is just
 * making a new one and comparing.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Csrf
{
    /**
     * Reads the secret key from the CSRF_KEY env var.
     *
     * @throws WebError if CSRF_KEY is not set
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

    /**
     * hash_equals() takes the same time whether the first or the last
     * character is wrong, so timing can't leak the right token.
     */
    public static function verify(string $sessionId, string $token): bool
    {
        return hash_equals(self::create($sessionId), $token);
    }
}
