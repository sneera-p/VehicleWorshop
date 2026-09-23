<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use Override;
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
    private ?string $tmpFile = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->tmpFile !== null && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        $this->tmpFile = null;
        parent::tearDown();
    }

    private function makeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vwork_resp_');
        $this->assertIsString($path);
        file_put_contents($path, $contents);
        return $this->tmpFile = $path;
    }

    // ---------------------------------------------------------------
    // make / named constructors
    // ---------------------------------------------------------------

    #[Test]
    public function make_keeps_the_given_status_and_headers(): void
    {
        $headers = [HttpHeaders::ContentType->value => ['application/json']];

        $response = Response::make('{}', $headers, HttpStatus::Found);

        $this->assertSame(HttpStatus::Found, $response->status);
        $this->assertSame($headers, $response->headers);
    }

    #[Test]
    #[TestWith(['<h1>hi</h1>'])]
    #[TestWith([''])]
    public function send_echoes_the_body_given_to_make(string $body): void
    {
        $response = Response::make($body, [], HttpStatus::Ok);

        $this->expectOutputString($body);
        $response->send();
    }

    #[Test]
    public function html_sets_an_html_content_type_and_defaults_to_200(): void
    {
        $response = Response::html('<p>x</p>');

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(
            [HttpHeaders::ContentType->value => ['text/html; charset=utf-8']],
            $response->headers,
        );
    }

    #[Test]
    public function html_honours_an_explicit_status(): void
    {
        $response = Response::html('', HttpStatus::MethodNotAllowed);

        $this->assertSame(HttpStatus::MethodNotAllowed, $response->status);
    }

    #[Test]
    public function text_sets_a_plain_text_content_type_and_defaults_to_200(): void
    {
        $response = Response::text('plain');

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(
            [HttpHeaders::ContentType->value => ['text/plain; charset=utf-8']],
            $response->headers,
        );

        $this->expectOutputString('plain');
        $response->send();
    }

    #[Test]
    public function error_is_a_text_response_with_the_given_status_and_message(): void
    {
        $response = Response::error(HttpStatus::MethodNotAllowed, 'nope');

        $this->assertSame(HttpStatus::MethodNotAllowed, $response->status);
        $this->assertSame(
            ['text/plain; charset=utf-8'],
            $response->headers[HttpHeaders::ContentType->value],
        );

        $this->expectOutputString('nope');
        $response->send();
    }

    #[Test]
    public function error_defaults_to_an_empty_body(): void
    {
        $this->expectOutputString('');
        Response::error(HttpStatus::MethodNotAllowed)->send();
    }

    #[Test]
    public function redirect_sets_location_and_defaults_to_found(): void
    {
        $response = Response::redirect('/jobs/42');

        $this->assertSame(HttpStatus::Found, $response->status);
        $this->assertSame(['/jobs/42'], $response->headers[HttpHeaders::Location->value]);

        $this->expectOutputString('');
        $response->send();
    }

    #[Test]
    public function redirect_honours_an_explicit_status(): void
    {
        $response = Response::redirect('/x', HttpStatus::Ok);

        $this->assertSame(HttpStatus::Ok, $response->status);
    }

    #[Test]
    public function method_not_allowed_lists_allowed_methods_comma_separated(): void
    {
        $response = Response::methodNotAllowed([HttpMethods::GET, HttpMethods::POST]);

        $this->assertSame(HttpStatus::MethodNotAllowed, $response->status);
        $this->assertSame(['GET, POST'], $response->headers[HttpHeaders::Allow->value]);
    }

    #[Test]
    public function method_not_allowed_with_no_methods_sends_an_empty_allow(): void
    {
        $response = Response::methodNotAllowed([]);

        $this->assertSame([''], $response->headers[HttpHeaders::Allow->value]);
    }

    #[Test]
    public function no_content_has_204_no_headers_and_no_body(): void
    {
        $response = Response::noContent();

        $this->assertSame(HttpStatus::NoContent, $response->status);
        $this->assertSame([], $response->headers);

        $this->expectOutputString('');
        $response->send();
    }

    #[Test]
    public function stream_is_200_with_the_given_headers(): void
    {
        $headers = [HttpHeaders::ContentType->value => ['text/event-stream']];

        $response = Response::stream(static function (): void {
        }, $headers);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame($headers, $response->headers);
    }

    #[Test]
    public function stream_does_not_run_the_emitter_until_send(): void
    {
        $calls = 0;
        $response = Response::stream(static function () use (&$calls): void {
            $calls++;
            echo "data: a\n\n";
        }, []);

        $this->assertSame(0, $calls);

        $this->expectOutputString("data: a\n\n");
        $response->send();

        $this->assertSame(1, $calls);
    }

    // ---------------------------------------------------------------
    // file
    // ---------------------------------------------------------------

    #[Test]
    public function file_sets_download_headers_from_the_file_on_disk(): void
    {
        $path = $this->makeTempFile('0123456789');

        $response = Response::file($path, 'invoice.pdf');

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(
            [
                HttpHeaders::ContentType->value => ['application/octet-stream'],
                HttpHeaders::ContentDisposition->value => ['attachment; filename="invoice.pdf"'],
                HttpHeaders::ContentLength->value => ['10'],
            ],
            $response->headers,
        );
    }

    #[Test]
    public function file_streams_the_file_contents_on_send(): void
    {
        $path = $this->makeTempFile("binary\x00payload");

        $response = Response::file($path, 'x.bin');

        $this->expectOutputString("binary\x00payload");
        $response->send();
    }

    #[Test]
    public function file_reads_the_file_at_send_time_not_construction_time(): void
    {
        $path = $this->makeTempFile('old');
        $response = Response::file($path, 'x.txt');

        file_put_contents($path, 'new');

        $this->expectOutputString('new');
        $response->send();
    }

    #[Test]
    public function file_throws_when_the_path_does_not_exist(): void
    {
        $this->expectException(WebError::class);
        Response::file(sys_get_temp_dir() . '/vwork_definitely_missing_' . uniqid(), 'x');
    }

    #[Test]
    public function file_throws_when_the_path_is_a_directory(): void
    {
        $this->expectException(WebError::class);
        Response::file(sys_get_temp_dir(), 'x');
    }

    // ---------------------------------------------------------------
    // changeStatus
    // ---------------------------------------------------------------

    #[Test]
    public function change_status_replaces_the_status_and_returns_the_same_instance(): void
    {
        $response = Response::text('x');

        $returned = $response->changeStatus(HttpStatus::NoContent);

        $this->assertSame($response, $returned);
        $this->assertSame(HttpStatus::NoContent, $response->status);
    }

    // ---------------------------------------------------------------
    // addCookie
    // ---------------------------------------------------------------

    #[Test]
    public function add_cookie_renders_defaults_path_samesite_lax_httponly(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'abc', secure: false);

        $this->assertSame(
            ['session_token=abc; Path=/; SameSite=Lax; HttpOnly'],
            $response->headers[HttpHeaders::SetCookie->value],
        );
    }

    #[Test]
    public function add_cookie_renders_every_attribute_in_order(): void
    {
        $response = Response::noContent()->addCookie(
            HttpCookies::SessionToken,
            'abc',
            secure: true,
            path: '/app',
            httpOnly: true,
            sameSite: CookieSameSite::Strict,
            maxAge: 3600,
            domain: 'example.com',
        );

        $this->assertSame(
            ['session_token=abc; Path=/app; SameSite=Strict; Domain=example.com; Max-Age=3600; HttpOnly; Secure'],
            $response->headers[HttpHeaders::SetCookie->value],
        );
    }

    #[Test]
    public function add_cookie_omits_httponly_when_disabled(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::CsrfToken, 'xyz', secure: false, httpOnly: false);

        $line = $response->headers[HttpHeaders::SetCookie->value][0];

        $this->assertStringNotContainsString('HttpOnly', $line);
        $this->assertStringNotContainsString('Secure', $line);
    }

    #[Test]
    public function add_cookie_emits_max_age_zero_rather_than_treating_it_as_unset(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'abc', secure: false, maxAge: 0);

        $this->assertStringContainsString(
            '; Max-Age=0',
            $response->headers[HttpHeaders::SetCookie->value][0],
        );
    }

    #[Test]
    public function add_cookie_appends_one_line_per_call(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'a', secure: false)
            ->addCookie(HttpCookies::CsrfToken, 'b', secure: false);

        $lines = $response->headers[HttpHeaders::SetCookie->value];

        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('session_token=a;', $lines[0]);
        $this->assertStringStartsWith('csrf_token=b;', $lines[1]);
    }

    #[Test]
    public function add_cookie_leaves_other_headers_alone(): void
    {
        $response = Response::html('x')
            ->addCookie(HttpCookies::SessionToken, 'a', secure: false);

        $this->assertSame(
            ['text/html; charset=utf-8'],
            $response->headers[HttpHeaders::ContentType->value],
        );
    }

    // ---------------------------------------------------------------
    // rmCookie
    // ---------------------------------------------------------------

    #[Test]
    public function rm_cookie_removes_only_the_named_cookie(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'a', secure: false)
            ->addCookie(HttpCookies::CsrfToken, 'b', secure: false)
            ->rmCookie(HttpCookies::SessionToken);

        $lines = $response->headers[HttpHeaders::SetCookie->value];

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('csrf_token=b;', $lines[0]);
    }

    #[Test]
    public function rm_cookie_removes_every_line_for_that_name(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'a', secure: false)
            ->addCookie(HttpCookies::SessionToken, 'b', secure: false, path: '/x')
            ->rmCookie(HttpCookies::SessionToken);

        $this->assertArrayNotHasKey(HttpHeaders::SetCookie->value, $response->headers);
    }

    #[Test]
    public function rm_cookie_drops_the_header_key_when_the_last_line_goes(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'a', secure: false)
            ->rmCookie(HttpCookies::SessionToken);

        $this->assertSame([], $response->headers);
    }

    #[Test]
    public function rm_cookie_on_a_response_without_cookies_is_a_no_op(): void
    {
        $response = Response::html('x');

        $returned = $response->rmCookie(HttpCookies::SessionToken);

        $this->assertSame($response, $returned);
        $this->assertSame(
            [HttpHeaders::ContentType->value => ['text/html; charset=utf-8']],
            $response->headers,
        );
    }

    // ---------------------------------------------------------------
    // expireCookie
    // ---------------------------------------------------------------

    #[Test]
    public function expire_cookie_sends_an_empty_already_expired_non_secure_cookie(): void
    {
        $response = Response::noContent()->expireCookie(HttpCookies::SessionToken);

        $this->assertSame(
            ['session_token=; Path=/; SameSite=Lax; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly'],
            $response->headers[HttpHeaders::SetCookie->value],
        );
    }

    #[Test]
    public function expire_cookie_carries_path_and_domain_so_the_browser_matches_it(): void
    {
        $response = Response::noContent()
            ->expireCookie(HttpCookies::SessionToken, path: '/app', domain: 'example.com');

        $line = $response->headers[HttpHeaders::SetCookie->value][0];

        $this->assertStringContainsString('Path=/app', $line);
        $this->assertStringContainsString('Domain=example.com', $line);
        $this->assertStringNotContainsString('Secure', $line);
    }

    #[Test]
    public function expire_cookie_appends_alongside_existing_cookies(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::CsrfToken, 'b', secure: false)
            ->expireCookie(HttpCookies::SessionToken);

        $this->assertCount(2, $response->headers[HttpHeaders::SetCookie->value]);
    }

    // ---------------------------------------------------------------
    // cookies (computed view)
    // ---------------------------------------------------------------

    #[Test]
    public function cookies_is_empty_when_nothing_was_set(): void
    {
        $this->assertSame([], Response::noContent()->cookies);
    }

    #[Test]
    public function cookies_reflects_names_and_values_without_attributes(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'abc', secure: true, maxAge: 60, domain: 'example.com')
            ->addCookie(HttpCookies::CsrfToken, 'xyz', secure: false);

        $this->assertSame(
            [
                HttpCookies::SessionToken->value => 'abc',
                HttpCookies::CsrfToken->value => 'xyz',
            ],
            $response->cookies,
        );
    }

    #[Test]
    public function cookies_is_recomputed_after_later_mutations(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'abc', secure: false);

        $this->assertSame([HttpCookies::SessionToken->value => 'abc'], $response->cookies);

        $response->rmCookie(HttpCookies::SessionToken);

        $this->assertSame([], $response->cookies);
    }

    #[Test]
    public function cookies_shows_an_expired_cookie_as_empty_string(): void
    {
        $response = Response::noContent()->expireCookie(HttpCookies::SessionToken);

        $this->assertSame([HttpCookies::SessionToken->value => ''], $response->cookies);
    }

    #[Test]
    public function cookies_later_line_wins_for_a_repeated_name(): void
    {
        $response = Response::noContent()
            ->addCookie(HttpCookies::SessionToken, 'first', secure: false)
            ->addCookie(HttpCookies::SessionToken, 'second', secure: false);

        $this->assertSame([HttpCookies::SessionToken->value => 'second'], $response->cookies);
    }
}
