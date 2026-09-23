<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\CookieSameSite;
use Vwork\Web\Http\HttpCookies;
use Vwork\Web\Http\HttpHeaders;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Response;
use Vwork\Web\WebError;

final class ResponseTest extends TestCase
{
    private const array HTML = [HttpHeaders::ContentType->value => ['text/html; charset=utf-8']];
    private const array TEXT = [HttpHeaders::ContentType->value => ['text/plain; charset=utf-8']];

    /**
     * @param list<mixed> $args
     * @param array<string, list<string>> $headers
     */
    #[Test]
    #[TestWith(['make', ['raw', [], HttpStatus::Found], HttpStatus::Found, [], 'raw'])]
    #[TestWith(['html', ['<p>x</p>'], HttpStatus::Ok, self::HTML, '<p>x</p>'])]
    #[TestWith(['html', ['', HttpStatus::MethodNotAllowed], HttpStatus::MethodNotAllowed, self::HTML, ''])]
    #[TestWith(['text', ['0'], HttpStatus::Ok, self::TEXT, '0'])]
    #[TestWith(['error', [HttpStatus::MethodNotAllowed, 'nope'], HttpStatus::MethodNotAllowed, self::TEXT, 'nope'])]
    #[TestWith(['error', [HttpStatus::MethodNotAllowed], HttpStatus::MethodNotAllowed, self::TEXT, ''])]
    #[TestWith(['redirect', ['/jobs/42'], HttpStatus::Found, [HttpHeaders::Location->value => ['/jobs/42']], ''])]
    #[TestWith(['redirect', ['/x', HttpStatus::Ok], HttpStatus::Ok, [HttpHeaders::Location->value => ['/x']], ''])]
    #[TestWith(['methodNotAllowed', [[HttpMethods::GET, HttpMethods::POST]], HttpStatus::MethodNotAllowed, [HttpHeaders::Allow->value => ['GET, POST']], ''])]
    #[TestWith(['methodNotAllowed', [[]], HttpStatus::MethodNotAllowed, [HttpHeaders::Allow->value => ['']], ''])]
    #[TestWith(['noContent', [], HttpStatus::NoContent, [], ''])]
    public function factories_build_status_headers_and_body(string $factory, array $args, HttpStatus $status, array $headers, string $body): void
    {
        /** @var Response */
        $response = Response::$factory(...$args);

        $this->assertSame($status, $response->status);
        $this->assertSame($headers, $response->headers);

        $this->expectOutputString($body);
        $response->send();
    }

    #[Test]
    public function stream_runs_the_emitter_only_on_send(): void
    {
        $calls = 0;
        $headers = [HttpHeaders::ContentType->value => ['text/event-stream']];
        $response = Response::stream(static function () use (&$calls): void {
            $calls++;
            echo "data: a\n\n";
        }, $headers);

        $this->assertSame([0, HttpStatus::Ok, $headers], [$calls, $response->status, $response->headers]);

        $this->expectOutputString("data: a\n\n");
        $response->send();
        $this->assertSame(1, $calls);
    }

    #[Test]
    #[TestWith(['0123456789', 'invoice.pdf'])]
    #[TestWith(["binary\x00payload", 'x.bin'])]
    #[TestWith(['', 'empty.txt'])]
    public function file_sets_download_headers_and_streams_contents(string $contents, string $name): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'vwork_');
        file_put_contents($path, $contents);

        try {
            $response = Response::file($path, $name);

            $this->assertSame(HttpStatus::Ok, $response->status);
            $this->assertSame([
                HttpHeaders::ContentType->value => ['application/octet-stream'],
                HttpHeaders::ContentDisposition->value => ["attachment; filename=\"{$name}\""],
                HttpHeaders::ContentLength->value => [(string) strlen($contents)],
            ], $response->headers);

            $this->expectOutputString($contents);
            $response->send();
        } finally {
            unlink($path);
        }
    }

    #[Test]
    #[TestWith(['/vwork_definitely_missing'])] // doesn't exist
    #[TestWith([''])]                          // the temp dir itself: exists, not a file
    public function file_throws_unless_path_is_a_regular_file(string $suffix): void
    {
        $this->expectException(WebError::class);
        Response::file(sys_get_temp_dir() . $suffix, 'x');
    }

    #[Test]
    public function change_status_mutates_in_place(): void
    {
        $response = Response::text('x');

        $this->assertSame($response, $response->changeStatus(HttpStatus::NoContent));
        $this->assertSame(HttpStatus::NoContent, $response->status);
    }

    /**
     * @param array<string, mixed> $opts
     */
    #[Test]
    #[TestWith([['secure' => false], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly'])]
    #[TestWith([['secure' => true], 'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure'])]
    #[TestWith([['secure' => false, 'httpOnly' => false], 'session_token=abc; Path=/; SameSite=Lax'])]
    #[TestWith([['secure' => false, 'maxAge' => 0], 'session_token=abc; Path=/; SameSite=Lax; Max-Age=0; HttpOnly'])]
    #[TestWith([
        ['secure' => true, 'path' => '/app', 'sameSite' => CookieSameSite::Strict, 'maxAge' => 3600, 'domain' => 'example.com'],
        'session_token=abc; Path=/app; SameSite=Strict; Domain=example.com; Max-Age=3600; HttpOnly; Secure',
    ])]
    public function add_cookie_renders_the_set_cookie_line(array $opts, string $expected): void
    {
        /** @phpstan-ignore argument.type */
        $response = Response::html('x')->addCookie(HttpCookies::SessionToken, 'abc', ...$opts);

        $this->assertSame([
            ...self::HTML,
            HttpHeaders::SetCookie->value => [$expected],
        ], $response->headers);
        $this->assertSame([HttpCookies::SessionToken->value => 'abc'], $response->cookies);
    }

    #[Test]
    public function add_cookie_appends_and_cookies_view_tracks_mutations(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'first', secure: false)
            ->addCookie(HttpCookies::CsrfToken, 'xyz', secure: true, domain: 'example.com')
            ->addCookie(HttpCookies::SessionToken, 'second', secure: false);

        $this->assertCount(3, $response->headers[HttpHeaders::SetCookie->value]);
        $this->assertSame([ // later line wins, attributes stripped
            HttpCookies::SessionToken->value => 'second',
            HttpCookies::CsrfToken->value => 'xyz',
        ], $response->cookies);

        $response->rmCookie(HttpCookies::SessionToken);
        $this->assertSame([HttpCookies::CsrfToken->value => 'xyz'], $response->cookies);
    }

    /**
     * @param list<string> $added
     * @param list<string> $remaining
     */
    #[Test]
    #[TestWith([[], 'session_token', []])]
    #[TestWith([['session_token'], 'session_token', []])]
    #[TestWith([['session_token', 'session_token'], 'session_token', []])]
    #[TestWith([['session_token', 'csrf_token'], 'session_token', ['csrf_token']])]
    #[TestWith([['csrf_token', 'session_token', 'csrf_token'], 'session_token', ['csrf_token', 'csrf_token']])]
    #[TestWith([['csrf_token'], 'session_token', ['csrf_token']])]
    public function rm_cookie_removes_every_line_for_that_name(array $added, string $removed, array $remaining): void
    {
        $response = Response::html('x');
        foreach ($added as $name) {
            $response->addCookie(HttpCookies::from($name), 'v', secure: false);
        }

        $this->assertSame($response, $response->rmCookie(HttpCookies::from($removed)));

        $lines = $response->headers[HttpHeaders::SetCookie->value] ?? null;
        if ($remaining === []) {
            $this->assertNull($lines, 'Set-Cookie key should be dropped entirely');
        } else {
            $this->assertIsList($lines);
            $this->assertSame($remaining, array_map(static fn (string $l) => explode('=', $l, 2)[0], $lines));
        }
        $this->assertSame(self::HTML[HttpHeaders::ContentType->value], $response->headers[HttpHeaders::ContentType->value]);
    }

    #[Test]
    #[TestWith(['/', null, 'session_token=; Path=/; SameSite=Lax; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly'])]
    #[TestWith(['/app', 'example.com', 'session_token=; Path=/app; SameSite=Lax; Domain=example.com; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly'])]
    public function expire_cookie_appends_an_empty_already_expired_line(string $path, ?string $domain, string $expected): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::CsrfToken, 'keep', secure: true)
            ->expireCookie(HttpCookies::SessionToken, $path, $domain);

        $this->assertSame($expected, $response->headers[HttpHeaders::SetCookie->value][1]);
        $this->assertSame([
            HttpCookies::CsrfToken->value => 'keep',
            HttpCookies::SessionToken->value => '',
        ], $response->cookies);
    }
}
