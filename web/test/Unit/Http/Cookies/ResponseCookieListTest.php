<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Cookies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\CookieSameSite;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\ResponseCookieList;
use Vwork\Web\WebError;

final class ResponseCookieListTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function lines(ResponseCookieList $list): array
    {
        return iterator_to_array($list->toLines(), false);
    }

    /**
     * @param array<string, mixed> $opts
     */
    #[Test]
    #[TestWith([[], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure'])]
    #[TestWith([['secure' => false], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly'])]
    #[TestWith([['httpOnly' => false], 'session_token=abc; Path=/; SameSite=Lax; Secure'])]
    #[TestWith([['maxAge' => 0], 'session_token=abc; Path=/; SameSite=Lax; Max-Age=0; HttpOnly; Secure'])]
    #[TestWith([['sameSite' => CookieSameSite::None], 'session_token=abc; Path=/; SameSite=None; HttpOnly; Secure'])]
    #[TestWith([
        ['path' => '/app', 'sameSite' => CookieSameSite::Strict, 'maxAge' => 3600, 'domain' => 'example.com'],
        'session_token=abc; Path=/app; SameSite=Strict; Domain=example.com; Max-Age=3600; HttpOnly; Secure',
    ])]
    public function renders_the_set_cookie_line(array $opts, string $expected): void
    {
        $list = new ResponseCookieList();
        /** @phpstan-ignore argument.type */
        $list->add(HttpCookies::SessionToken, 'abc', ...$opts);

        $this->assertSame([$expected], self::lines($list));
    }

    #[Test]
    #[TestWith(['a b;c', 'a%20b%3Bc'])]
    #[TestWith(['x; Domain=evil.com', 'x%3B%20Domain%3Devil.com'])]
    public function url_encodes_the_value_on_the_wire_but_reads_it_back_raw(string $value, string $encoded): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, $value);

        $this->assertStringStartsWith("session_token={$encoded};", self::lines($list)[0]);
        $this->assertSame($value, $list[HttpCookies::SessionToken]);
    }

    #[Test]
    public function adding_the_same_name_again_replaces_it(): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, 'first');
        $list->add(HttpCookies::CsrfToken, 'xyz');
        $list->add(HttpCookies::SessionToken, 'second');

        $this->assertCount(2, self::lines($list));
        $this->assertSame('second', $list[HttpCookies::SessionToken]);
    }

    #[Test]
    public function rm_drops_only_that_cookie(): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, 'abc');
        $list->add(HttpCookies::CsrfToken, 'xyz');

        $list->rm(HttpCookies::SessionToken);
        $list->rm(HttpCookies::RefreshToken); // never added: no-op

        $this->assertFalse(isset($list[HttpCookies::SessionToken]));
        $this->assertNull($list[HttpCookies::SessionToken]);
        $this->assertSame('xyz', $list[HttpCookies::CsrfToken]);
    }

    #[Test]
    #[TestWith(['/', null, 'session_token=; Path=/; SameSite=Lax; Max-Age=0; HttpOnly; Secure'])]
    #[TestWith(['/app', 'example.com', 'session_token=; Path=/app; SameSite=Lax; Domain=example.com; Max-Age=0; HttpOnly; Secure'])]
    public function expire_sends_an_empty_zero_max_age_cookie(string $path, ?string $domain, string $expected): void
    {
        $list = new ResponseCookieList();
        $list->expire(HttpCookies::SessionToken, $path, $domain);

        $this->assertSame([$expected], self::lines($list));
    }

    #[Test]
    #[TestWith(['/a;b', null])]
    #[TestWith(['/a b', null])]
    #[TestWith(["/a\r\nX: y", null])]
    #[TestWith(['/', 'example.com; Max-Age=999'])]
    #[TestWith(['/', 'a,b.com'])]
    public function rejects_a_path_or_domain_that_could_inject_attributes(string $path, ?string $domain): void
    {
        $this->expectException(WebError::class);
        (new ResponseCookieList())->add(HttpCookies::SessionToken, 'abc', path: $path, domain: $domain);
    }

    #[Test]
    public function rejects_same_site_none_without_secure(): void
    {
        $this->expectException(WebError::class);
        (new ResponseCookieList())->add(HttpCookies::SessionToken, 'abc', secure: false, sameSite: CookieSameSite::None);
    }

    #[Test]
    public function refuses_array_set(): void
    {
        $list = new ResponseCookieList();

        $this->expectException(WebError::class);
        $list[HttpCookies::SessionToken] = 'abc';
    }

    #[Test]
    public function refuses_array_unset(): void
    {
        $list = new ResponseCookieList();

        $this->expectException(WebError::class);
        unset($list[HttpCookies::SessionToken]);
    }
}
