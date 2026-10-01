<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

/**
 * Contains only the Cookies used by web/
 *
 * @author Senira <senirahan@gmail.com>
 */
enum HttpCookies: string
{
    case SessionToken = 'session_token';
    case RefreshToken = 'refresh_token';

    public function toLine(string $value, string $path, CookieSitePolicies $sameSite, ?int $maxAge, bool $secure): string
    {
        $line = "{$this->value}=" . rawurlencode($value)
            . "; Path={$path}"
            . "; SameSite={$sameSite->value}"
            . '; HttpOnly';

        if ($maxAge !== null) {
            $line .= "; Max-Age={$maxAge}";
        }

        if ($secure) {
            $line .= '; Secure';
        }

        return $line;
    }
}
