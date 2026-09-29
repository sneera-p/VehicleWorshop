<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Cookies;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\RequestCookieList;
use Vwork\Web\Http\Headers\HttpHeaderList;
use Vwork\Web\WebError;

final class RequestCookieListTest extends TestCase
{
    private static function parse(?string $header): RequestCookieList
    {
        return RequestCookieList::fromHeader(
            HttpHeaderList::fromArray($header === null ? [] : ['Cookie' => [$header]]),
        );
    }

    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[TestWith([null, []])]
    #[TestWith(['', []])]
    #[TestWith(['session_token=abc; csrf_token=xyz', ['session_token' => 'abc', 'csrf_token' => 'xyz']])]
    #[TestWith(['session_token=abc; ga_tracking=xyz', ['session_token' => 'abc']])]
    #[TestWith(['session_token=abc;;garbage; ', ['session_token' => 'abc']])]
    #[TestWith(['session_token=', ['session_token' => '']])]
    #[TestWith(['session_token', ['session_token' => '']])]
    #[TestWith(['session_token=a=b', ['session_token' => 'a=b']])]
    #[TestWith(['session_token=a%20b%3Bc', ['session_token' => 'a b;c']])]
    #[TestWith(['session_token=old; session_token=new', ['session_token' => 'new']])]
    public function parses_known_names_from_the_cookie_header(?string $header, array $expected): void
    {
        $this->assertSame($expected, self::parse($header)->list);
    }

    #[Test]
    public function reads_by_enum_key(): void
    {
        $cookies = self::parse('session_token=abc');

        $this->assertSame('abc', $cookies[HttpCookies::SessionToken]);
        $this->assertTrue(isset($cookies[HttpCookies::SessionToken]));
        $this->assertNull($cookies[HttpCookies::CsrfToken]);
        $this->assertFalse(isset($cookies[HttpCookies::CsrfToken]));
    }

    #[Test]
    public function refuses_to_set(): void
    {
        $cookies = self::parse('session_token=abc');

        $this->expectException(WebError::class);
        $cookies[HttpCookies::SessionToken] = 'evil';
    }

    #[Test]
    public function refuses_to_unset(): void
    {
        $cookies = self::parse('session_token=abc');

        $this->expectException(WebError::class);
        unset($cookies[HttpCookies::SessionToken]);
    }
}
