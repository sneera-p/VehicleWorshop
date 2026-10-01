<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\CookieSitePolicies;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Headers\ResponseHeaders;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Response;
use Vwork\Web\WebError;

/**
 * Response's own behaviour: factories, headers, and delegation to
 * its cookie list. Set-Cookie rendering is covered by ResponseCookieListTest.
 */
final class ResponseTest extends TestCase
{
    private const array HTML = ['Content-Type' => ['text/html; charset=utf-8']];
    private const array TEXT = ['Content-Type' => ['text/plain; charset=utf-8']];
    private const array JSON = ['Content-Type' => ['application/json; charset=utf-8']];
    private const array CBOR = ['Content-Type' => ['application/cbor']];

    /**
     * @param list<mixed> $args
     * @param array<string, list<string>> $headers
     */
    #[Test]
    #[TestWith(['make', ['raw', [], HttpStatus::Found], HttpStatus::Found, [], 'raw'])]
    #[TestWith(['html', ['<p>x</p>'], HttpStatus::Ok, self::HTML, '<p>x</p>'])]
    #[TestWith(['html', ['', HttpStatus::NotFound], HttpStatus::NotFound, self::HTML, ''])]
    #[TestWith(['text', ['0'], HttpStatus::Ok, self::TEXT, '0'])]
    #[TestWith(['json', [['a' => 1]], HttpStatus::Ok, self::JSON, '{"a":1}'])]
    #[TestWith(['json', [['path' => '/jobs/42']], HttpStatus::Ok, self::JSON, '{"path":"/jobs/42"}'])]
    #[TestWith(['json', [['name' => 'රසීද']], HttpStatus::Ok, self::JSON, '{"name":"රසීද"}'])]
    #[TestWith(['json', [['s' => "a\u{2028}b"]], HttpStatus::Ok, self::JSON, "{\"s\":\"a\u{2028}b\"}"])]
    #[TestWith(['json', [[], HttpStatus::Created], HttpStatus::Created, self::JSON, '[]'])]
    #[TestWith(['cbor', [['a' => 1]], HttpStatus::Ok, self::CBOR, "\xa1\x61a\x01"])]
    #[TestWith(['cbor', [['a' => 'b']], HttpStatus::Ok, self::CBOR, "\xa1\x61a\x61b"])]
    #[TestWith(['cbor', [[], HttpStatus::Created], HttpStatus::Created, self::CBOR, "\x80"])]
    #[TestWith(['error', [HttpStatus::Conflict, 'nope'], HttpStatus::Conflict, self::TEXT, 'nope'])]
    #[TestWith(['error', [HttpStatus::InternalServerError], HttpStatus::InternalServerError, self::TEXT, ''])]
    #[TestWith(['redirect', ['/jobs/42'], HttpStatus::Found, ['Location' => ['/jobs/42']], ''])]
    #[TestWith(['redirect', ['/jobs', HttpStatus::SeeOther], HttpStatus::SeeOther, ['Location' => ['/jobs']], ''])]
    #[TestWith(['methodNotAllowed', [[HttpMethods::GET, HttpMethods::POST]], HttpStatus::MethodNotAllowed, ['Allow' => ['GET, POST']], ''])]
    #[TestWith(['noContent', [], HttpStatus::NoContent, [], ''])]
    public function factories_build_status_headers_and_body(string $factory, array $args, HttpStatus $status, array $headers, string $body): void
    {
        /** @var Response */
        $response = Response::$factory(...$args);

        $this->assertSame($status, $response->status);
        $this->assertSame($headers, $response->headers->list);

        $this->expectOutputString($body);
        $response->send(true);
    }

    #[Test]
    public function json_throws_instead_of_sending_a_false_body(): void
    {
        $this->expectException(\JsonException::class);
        Response::json(['bad' => "\xff"]); // invalid UTF-8
    }

    #[Test]
    public function cbor_throws_on_invalid_utf8_in_text_mode(): void
    {
        $this->expectException(\Cbor\Exception::class);
        $this->expectExceptionCode(CBOR_ERROR_UTF8);
        Response::cbor(['bad' => "\xff"]);
    }

    #[Test]
    public function cbor_encodes_strings_and_keys_as_text_not_bytes(): void
    {
        $response = Response::cbor(['name' => 'x']);

        ob_start();
        $response->send(true);
        $body = (string) ob_get_clean();

        // map(1), then major type 3 (text, 0x6_) — byte strings would be major type 2 (0x4_)
        $this->assertSame("\xa1\x64name\x61x", $body);
        $this->assertSame(['name' => 'x'], cbor_decode($body, CBOR_TEXT | CBOR_KEY_TEXT | CBOR_MAP_AS_ARRAY));
    }

    #[Test]
    public function redirect_rejects_a_location_that_could_split_the_header(): void
    {
        $this->expectException(WebError::class);
        Response::redirect("/x\r\nSet-Cookie: session_token=evil");
    }

    #[Test]
    public function stream_runs_the_emitter_only_on_send(): void
    {
        $calls = 0;
        $response = Response::stream(static function () use (&$calls): void {
            $calls++;
            echo "data: a\n\n";
        }, ['Content-Type' => ['text/event-stream']]);

        $this->assertSame(0, $calls);
        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(['Content-Type' => ['text/event-stream']], $response->headers->list);

        $this->expectOutputString("data: a\n\n");
        $response->send(true);
        $this->assertSame(1, $calls);
    }

    #[Test]
    #[TestWith(['0123456789', 'invoice.pdf', 'attachment; filename="invoice.pdf"; filename*=UTF-8\'\'invoice.pdf'])]
    #[TestWith(["binary\x00payload", 'x.bin', 'attachment; filename="x.bin"; filename*=UTF-8\'\'x.bin'])]
    #[TestWith(['', 'invoice "final".pdf', 'attachment; filename="invoice \"final\".pdf"; filename*=UTF-8\'\'invoice%20%22final%22.pdf'])]
    #[TestWith(['x', 'රසීද.pdf', 'attachment; filename="____________.pdf"; filename*=UTF-8\'\'%E0%B6%BB%E0%B7%83%E0%B7%93%E0%B6%AF.pdf'])]
    public function file_sets_download_headers_and_streams_contents(string $contents, string $name, string $disposition): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'vwork_');
        file_put_contents($path, $contents);

        try {
            $response = Response::file($path, $name);

            $this->assertSame(HttpStatus::Ok, $response->status);
            $this->assertSame([
                'Content-Type' => ['application/octet-stream'],
                'Content-Disposition' => [$disposition],
                'Content-Length' => [(string) strlen($contents)],
            ], $response->headers->list);

            $this->expectOutputString($contents);
            $response->send(true);
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

    #[Test]
    public function add_and_rm_header_mutate_in_place(): void
    {
        $response = Response::html('x');

        $this->assertSame($response, $response->addHeader(ResponseHeaders::CacheControl, 'no-store'));
        $this->assertSame(['no-store'], $response->headers[ResponseHeaders::CacheControl]);

        $this->assertSame($response, $response->rmHeader(ResponseHeaders::CacheControl));
        $this->assertSame(self::HTML, $response->headers->list);
    }

    #[Test]
    public function cookie_methods_delegate_to_the_cookie_list(): void
    {
        $response = Response::noContent();

        $this->assertSame($response, $response->addCookie(HttpCookies::SessionToken, 'abc'));
        $this->assertSame($response, $response->addCookie(HttpCookies::RefreshToken, 'xyz', path: '/auth'));
        $this->assertSame($response, $response->rmCookie(HttpCookies::RefreshToken));
        $this->assertSame($response, $response->expireCookie(HttpCookies::RefreshToken, '/auth'));

        $this->assertSame([
            'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure',
            'refresh_token=; Path=/auth; SameSite=Lax; HttpOnly; Max-Age=0; Secure',
        ], iterator_to_array($response->cookies->toLines(true), false));
        $this->assertSame([], $response->headers->list); // cookies never leak into the header list
    }

    #[Test]
    public function add_cookie_passes_every_attribute_through(): void
    {
        $response = Response::noContent()->addCookie(
            HttpCookies::RefreshToken,
            'xyz',
            path: '/auth',
            sameSite: CookieSitePolicies::Strict,
            maxAge: 3600,
        );

        $this->assertSame(
            ['refresh_token=xyz; Path=/auth; SameSite=Strict; HttpOnly; Max-Age=3600; Secure'],
            iterator_to_array($response->cookies->toLines(true), false),
        );
    }
}
