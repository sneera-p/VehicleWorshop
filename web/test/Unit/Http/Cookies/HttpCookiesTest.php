<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Cookies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\CookieSitePolicies;
use Vwork\Web\Http\Cookies\HttpCookies;

/**
 * How one cookie becomes a Set-Cookie line. Which cookies get sent,
 * and the checks on their attributes, are Response's job (ResponseTest).
 */
final class HttpCookiesTest extends TestCase
{
    #[Test]
    #[TestWith(['/', CookieSitePolicies::Lax, null, 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure'])]
    #[TestWith(['/', CookieSitePolicies::Lax, 0, 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Max-Age=0; Secure'])]
    #[TestWith(['/', CookieSitePolicies::None, null, 'session_token=abc; Path=/; SameSite=None; HttpOnly; Secure'])]
    #[TestWith(['/auth', CookieSitePolicies::Strict, 3600, 'session_token=abc; Path=/auth; SameSite=Strict; HttpOnly; Max-Age=3600; Secure'])]
    public function renders_every_attribute(string $path, CookieSitePolicies $sameSite, ?int $maxAge, string $expected): void
    {
        $this->assertSame($expected, HttpCookies::SessionToken->toLine('abc', $path, $sameSite, $maxAge, true));
    }

    #[Test]
    public function secure_is_decided_by_the_caller(): void
    {
        // the flag belongs to the app, not the cookie
        $this->assertSame(
            'session_token=abc; Path=/; SameSite=Lax; HttpOnly',
            HttpCookies::SessionToken->toLine('abc', '/', CookieSitePolicies::Lax, null, false),
        );
    }

    #[Test]
    #[TestWith(['a b;c', 'a%20b%3Bc'])]
    #[TestWith(['x; Domain=evil.com', 'x%3B%20Domain%3Devil.com'])] // can't smuggle in an attribute
    public function url_encodes_the_value(string $value, string $encoded): void
    {
        $this->assertStringStartsWith(
            "session_token={$encoded};",
            HttpCookies::SessionToken->toLine($value, '/', CookieSitePolicies::Lax, null, true),
        );
    }
}
