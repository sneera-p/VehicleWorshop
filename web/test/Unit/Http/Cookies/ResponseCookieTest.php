<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Cookies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\CookieSitePolicies;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\ResponseCookie;
use Vwork\Web\WebError;

/**
 * One cookie: the checks on its attributes, and how it becomes a
 * Set-Cookie line. Which cookies a response sends is ResponseTest's job.
 */
final class ResponseCookieTest extends TestCase
{
    #[Test]
    #[TestWith(['/', CookieSitePolicies::Lax, null, 'session_token=abc; Path=/; SameSite=Lax; HttpOnly'])]
    #[TestWith(['/', CookieSitePolicies::Lax, 0, 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Max-Age=0'])]
    #[TestWith(['/', CookieSitePolicies::None, null, 'session_token=abc; Path=/; SameSite=None; HttpOnly'])]
    #[TestWith(['/auth', CookieSitePolicies::Strict, 3600, 'session_token=abc; Path=/auth; SameSite=Strict; HttpOnly; Max-Age=3600'])]
    public function renders_every_attribute(string $path, CookieSitePolicies $sameSite, ?int $maxAge, string $expected): void
    {
        $cookie = new ResponseCookie(HttpCookies::SessionToken, 'abc', $path, $sameSite, $maxAge);

        $this->assertSame($expected, $cookie->toLine(false));
        $this->assertSame("{$expected}; Secure", $cookie->toLine(true));
    }

    #[Test]
    public function secure_is_decided_by_the_caller(): void
    {
        $cookie = new ResponseCookie(HttpCookies::RefreshToken, 'Secure'); // a value that looks like the flag

        $this->assertStringEndsWith('; Secure', $cookie->toLine(true));
        $this->assertStringEndsNotWith('; Secure', $cookie->toLine(false));
    }

    #[Test]
    #[TestWith(['a b;c', 'a%20b%3Bc'])]
    #[TestWith(['x; Domain=evil.com', 'x%3B%20Domain%3Devil.com'])] // can't smuggle in an attribute
    public function url_encodes_the_value(string $value, string $encoded): void
    {
        $cookie = new ResponseCookie(HttpCookies::SessionToken, $value);

        $this->assertStringStartsWith("session_token={$encoded};", $cookie->toLine(true));
    }

    #[Test]
    #[TestWith(['/a;b'])]         // would start a new attribute
    #[TestWith(['/a,b'])]
    #[TestWith(['/a b'])]
    #[TestWith(["/a\r\nX: y"])]   // would split the header
    #[TestWith(["/a\0"])]
    #[TestWith(['auth'])]         // must start with /
    #[TestWith([''])]
    public function rejects_a_path_that_could_inject_attributes(string $path): void
    {
        $this->expectException(WebError::class);
        new ResponseCookie(HttpCookies::SessionToken, 'x', $path);
    }

    #[Test]
    public function rejects_a_negative_max_age(): void
    {
        $this->expectException(WebError::class);
        new ResponseCookie(HttpCookies::SessionToken, 'x', maxAge: -1);
    }
}
