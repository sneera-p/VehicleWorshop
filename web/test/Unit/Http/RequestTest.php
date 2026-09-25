<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\Request;
use Vwork\Web\Test\Mocks\MockInputStream;
use Vwork\Web\WebException;

/**
 * Request::fromGlobals() only: reading the superglobals and handing the
 * pieces to the right place. Header and cookie parsing rules are covered
 * by HttpHeaderListTest and RequestCookieListTest.
 */
final class RequestTest extends TestCase
{
    /** @var list<array<mixed, mixed>> */
    private array $globalState;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->globalState = [$_SERVER, $_GET, $_POST, $_FILES];
    }

    #[Override]
    protected function tearDown(): void
    {
        MockInputStream::restore();
        [$_SERVER, $_GET, $_POST, $_FILES] = $this->globalState;
        parent::tearDown();
    }

    /**
     * Sets the superglobals and builds a Request from them. $server is
     * merged over a minimal valid baseline.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    private static function request(
        array $server = [],
        array $get = [],
        array $post = [],
        array $files = [],
        string $body = '',
    ): Request {
        MockInputStream::install($body);
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '', 'REQUEST_URI' => '/', ...$server];
        $_GET = $get;
        $_POST = $post;
        $_FILES = $files;

        return Request::fromGlobals();
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['{"job":42}'])]
    #[TestWith(["binary\x00\xff payload\r\n"])]
    public function captures_the_raw_body_verbatim(string $body): void
    {
        $this->assertSame($body, self::request(body: $body)->body);
    }

    #[Test]
    #[TestWith(['GET', HttpMethods::GET])]
    #[TestWith(['POST', HttpMethods::POST])]
    #[TestWith(['get', HttpMethods::GET])]
    #[TestWith(['Patch', HttpMethods::PATCH])]
    public function resolves_the_method_case_insensitively(string $raw, HttpMethods $expected): void
    {
        $this->assertSame($expected, self::request(['REQUEST_METHOD' => $raw])->method);
    }

    #[Test]
    #[TestWith(['BREW'])]
    #[TestWith(['TRACE'])]
    #[TestWith([''])]
    public function throws_on_an_unsupported_method(string $raw): void
    {
        $this->expectException(WebException::class);
        self::request(['REQUEST_METHOD' => $raw]);
    }

    #[Test]
    #[TestWith(['/jobs/42', '/jobs/42'])]
    #[TestWith(['/jobs/42?tab=notes&x=1', '/jobs/42'])]
    #[TestWith(['/?', '/'])]
    #[TestWith(['/a?b?c', '/a'])]
    #[TestWith(['', ''])]
    public function path_is_the_uri_without_its_query_string(string $uri, string $expected): void
    {
        $this->assertSame($expected, self::request(['REQUEST_URI' => $uri])->path);
    }

    #[Test]
    public function passes_query_form_data_and_ip_through(): void
    {
        $request = self::request(
            server: ['REMOTE_ADDR' => '203.0.113.7'],
            get: ['tab' => 'notes', 'x' => '1'],
            post: ['name' => 'Oil change', 'urgent' => 'true'],
        );

        $this->assertSame('203.0.113.7', $request->ip);
        $this->assertSame(['tab' => 'notes', 'x' => '1'], $request->query);
        $this->assertSame(['name' => 'Oil change', 'urgent' => 'true'], $request->formData);
    }

    #[Test]
    public function headers_are_read_from_server(): void
    {
        $request = self::request(['CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'phpunit']);

        $this->assertSame('application/json', $request->headers[HttpHeaders::ContentType]);
        $this->assertSame('phpunit', $request->headers[HttpHeaders::UserAgent]);
    }

    #[Test]
    public function cookies_are_parsed_from_the_cookie_header(): void
    {
        $request = self::request(['HTTP_COOKIE' => 'session_token=abc; csrf_token=xyz']);

        $this->assertSame('abc', $request->cookies[HttpCookies::SessionToken]);
        $this->assertSame('xyz', $request->cookies[HttpCookies::CsrfToken]);
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, string> $expected field key => original file name
     */
    #[Test]
    #[TestWith([[], []])]
    #[TestWith([
        ['photo' => ['name' => 'damage.jpg', 'tmp_name' => '/tmp/p', 'type' => 'image/jpeg', 'size' => 1024, 'error' => UPLOAD_ERR_OK]],
        ['photo' => 'damage.jpg'],
    ])]
    #[TestWith([
        ['photos' => [
            'name' => ['a.jpg', 'b.jpg'], 'tmp_name' => ['/tmp/a', '/tmp/b'], 'type' => ['image/jpeg', 'image/jpeg'],
            'size' => [100, 200], 'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        ]],
        ['photos[0]' => 'a.jpg', 'photos[1]' => 'b.jpg'],
    ])]
    public function flattens_uploaded_files(array $files, array $expected): void
    {
        $actual = array_map(static fn ($f) => $f->name, self::request(files: $files)->files);

        $this->assertSame($expected, $actual);
    }
}
