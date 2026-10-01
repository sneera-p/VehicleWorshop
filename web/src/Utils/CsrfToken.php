<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Random\Randomizer;

/**
 * A CSRF token is an HMAC of the session ID, so nothing is stored.
 * Each call hides it under fresh random bytes (BREACH), so the same
 * session gets a different-looking token every time. Verifying strips
 * the mask and compares against a freshly made HMAC.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class CsrfToken implements IUtility
{
    private const int SIZE = 32; // sha256, raw bytes

    public function __construct(
        private string $secret,
        private Randomizer $random
    ) {
    }

    public function create(string $id): string
    {
        $mask = $this->random->getBytes(self::SIZE);

        return self::encode($mask . ($mask ^ $this->createToken($id)));
    }

    public function verify(string $id, string $token): bool
    {
        $raw = self::decode($token);
        if ($raw === null || strlen($raw) !== 2 * self::SIZE) {
            return false;
        }

        $mask = substr($raw, 0, self::SIZE);
        $masked = substr($raw, self::SIZE);

        return $this->verifyToken($id, $mask ^ $masked);
    }

    private function createToken(string $id): string
    {
        return hash_hmac('sha256', $id, $this->secret, true);
    }

    private function verifyToken(string $id, string $token): bool
    {
        return hash_equals($this->createToken($id), $token);
    }

    // base64url without padding: safe in an HTML attribute and a header
    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $token): ?string
    {
        $bytes = base64_decode(strtr($token, '-_', '+/'), true);
        return $bytes === false ? null : $bytes;
    }
}
