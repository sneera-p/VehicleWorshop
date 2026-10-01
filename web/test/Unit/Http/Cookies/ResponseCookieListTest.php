<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Cookies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\CookieSitePolicies;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\ResponseCookieList;
use Vwork\Web\WebError;

final class ResponseCookieListTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function lines(ResponseCookieList $list, bool $secure = true): array
    {
        return iterator_to_array($list->toLines($secure), false);
    }

    /**
     * @param array<string, mixed> $opts
     */
    #[Test]
    #[TestWith([[], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure'])]
    #[TestWith([['maxAge' => 0], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Max-Age=0; Secure'])]
    #[TestWith([['sameSite' => CookieSitePolicies::None], 'session_token=abc; Path=/; SameSite=None; HttpOnly; Secure'])]
    #[TestWith([
        ['path' => '/auth', 'sameSite' => CookieSitePolicies::Strict, 'maxAge' => 3600],
        'session_token=abc; Path=/auth; SameSite=Strict; HttpOnly; Max-Age=3600; Secure',
    ])]
    public function renders_the_set_cookie_line(array $opts, string $expected): void
    {
        $list = new ResponseCookieList();
        /** @phpstan-ignore argument.type */
        $list->add(HttpCookies::SessionToken, 'abc', ...$opts);

        $this->assertSame([$expected], self::lines($list));
    }

    #[Test]
    public function secure_is_decided_at_send_time(): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, 'abc');

        // same list, two answers: the flag belongs to the app, not the cookie
        $this->assertSame(['session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure'], self::lines($list, true));
        $this->assertSame(['session_token=abc; Path=/; SameSite=Lax; HttpOnly'], self::lines($list, false));
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
        $list->add(HttpCookies::RefreshToken, 'xyz');
        $list->add(HttpCookies::SessionToken, 'second');

        $this->assertCount(2, self::lines($list));
        $this->assertSame('second', $list[HttpCookies::SessionToken]);
    }

    #[Test]
    public function rm_drops_only_that_cookie(): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, 'abc');
        $list->add(HttpCookies::RefreshToken, 'xyz');

        $list->rm(HttpCookies::SessionToken);
        $list->rm(HttpCookies::SessionToken); // already gone: no-op

        $this->assertFalse(isset($list[HttpCookies::SessionToken]));
        $this->assertNull($list[HttpCookies::SessionToken]);
        $this->assertSame('xyz', $list[HttpCookies::RefreshToken]);
    }

    #[Test]
    #[TestWith(['/', 'session_token=; Path=/; SameSite=Lax; HttpOnly; Max-Age=0; Secure'])]
    #[TestWith(['/auth', 'session_token=; Path=/auth; SameSite=Lax; HttpOnly; Max-Age=0; Secure'])]
    public function expire_sends_an_empty_zero_max_age_cookie(string $path, string $expected): void
    {
        $list = new ResponseCookieList();
        $list->expire(HttpCookies::SessionToken, $path);

        $this->assertSame([$expected], self::lines($list));
    }

    #[Test]
    public function expire_replaces_a_cookie_added_earlier(): void
    {
        $list = new ResponseCookieList();
        $list->add(HttpCookies::SessionToken, 'abc', maxAge: 3600);
        $list->expire(HttpCookies::SessionToken);

        $this->assertSame(['session_token=; Path=/; SameSite=Lax; HttpOnly; Max-Age=0; Secure'], self::lines($list));
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
        (new ResponseCookieList())->add(HttpCookies::SessionToken, 'abc', path: $path);
    }

    #[Test]
    public function rejects_a_negative_max_age(): void
    {
        $this->expectException(WebError::class);
        (new ResponseCookieList())->add(HttpCookies::SessionToken, 'abc', maxAge: -1);
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
