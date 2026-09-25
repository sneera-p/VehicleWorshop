<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Response;
use Vwork\Web\WebError;

/**
 * Response's own behaviour: factories, header guards, and delegation to
 * its cookie list. Set-Cookie rendering is covered by ResponseCookieListTest.
 */
final class ResponseTest extends TestCase
{
    private const array HTML = ['Content-Type' => ['text/html; charset=utf-8']];
    private const array TEXT = ['Content-Type' => ['text/plain; charset=utf-8']];

    /**
     * @param list<mixed> $args
     * @param array<string, list<string>> $headers
     */
    #[Test]
    #[TestWith(['make', ['raw', [], HttpStatus::Found], HttpStatus::Found, [], 'raw'])]
    #[TestWith(['html', ['<p>x</p>'], HttpStatus::Ok, self::HTML, '<p>x</p>'])]
    #[TestWith(['html', ['', HttpStatus::NotFound], HttpStatus::NotFound, self::HTML, ''])]
    #[TestWith(['text', ['0'], HttpStatus::Ok, self::TEXT, '0'])]
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
        $response->send();
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
        $response->send();
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

    #[Test]
    public function add_and_rm_header_mutate_in_place(): void
    {
        $response = Response::html('x');

        $this->assertSame($response, $response->addHeader(HttpHeaders::CacheControl, 'no-store'));
        $this->assertSame(['no-store'], $response->headers[HttpHeaders::CacheControl]);

        $this->assertSame($response, $response->rmHeader(HttpHeaders::CacheControl));
        $this->assertSame(self::HTML, $response->headers->list);
    }

    #[Test]
    #[TestWith([HttpHeaders::Authorization])] // request-only
    #[TestWith([HttpHeaders::Cookie])]        // request-only
    #[TestWith([HttpHeaders::SetCookie])]     // must go through the cookie methods
    public function add_header_refuses_headers_that_do_not_belong_on_a_response(HttpHeaders $header): void
    {
        $this->expectException(WebError::class);
        Response::html('x')->addHeader($header, 'v');
    }

    #[Test]
    public function cookie_methods_delegate_to_the_cookie_list(): void
    {
        $response = Response::noContent();

        $this->assertSame($response, $response->addCookie(HttpCookies::SessionToken, 'abc'));
        $this->assertSame($response, $response->addCookie(HttpCookies::CsrfToken, 'xyz'));
        $this->assertSame($response, $response->rmCookie(HttpCookies::CsrfToken));
        $this->assertSame($response, $response->expireCookie(HttpCookies::RefreshToken, '/auth'));

        $this->assertSame([
            'session_token=abc; Path=/; SameSite=Lax; HttpOnly; Secure',
            'refresh_token=; Path=/auth; SameSite=Lax; Max-Age=0; HttpOnly; Secure',
        ], iterator_to_array($response->cookies->toLines(), false));
        $this->assertSame([], $response->headers->list); // cookies never leak into the header list
    }
}
