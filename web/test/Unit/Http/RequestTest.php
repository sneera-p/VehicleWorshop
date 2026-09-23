<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\HttpCookies;
use Vwork\Web\Http\HttpHeaders;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\Request;
use Vwork\Web\Test\Mocks\MockInputStream;
use Vwork\Web\WebError;
use Vwork\Web\WebException;

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
    #[TestWith(['Post', HttpMethods::POST])]
    public function resolves_the_method_case_insensitively(string $raw, HttpMethods $expected): void
    {
        $this->assertSame($expected, self::request(['REQUEST_METHOD' => $raw])->method);
    }

    #[Test]
    #[TestWith(['BREW'])]
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

    /**
     * @param array<string, string> $server
     * @param array<string, list<string>> $expected
     */
    #[Test]
    #[TestWith([[], []])]
    #[TestWith([['HTTP_X_MADE_UP' => 'whatever', 'SERVER_NAME' => 'localhost'], []])]
    #[TestWith([
        ['HTTP_CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => 'phpunit', 'HTTP_X_MADE_UP' => 'x'],
        [HttpHeaders::ContentType->value => ['application/json'], HttpHeaders::UserAgent->value => ['phpunit']],
    ])]
    public function keeps_only_known_http_headers(array $server, array $expected): void
    {
        $headers = self::request($server)->headers;

        ksort($headers);
        ksort($expected);
        $this->assertSame($expected, $headers);
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

    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[TestWith([null, []])]
    #[TestWith(['', []])]
    #[TestWith(['session_token=abc; csrf_token=xyz', ['session_token' => 'abc', 'csrf_token' => 'xyz']])]
    #[TestWith(['session_token=abc; ga_tracking=xyz', ['session_token' => 'abc']])]
    #[TestWith(['  session_token = abc ;;garbage; ', ['session_token' => 'abc']])]
    #[TestWith(['session_token=', ['session_token' => '']])]
    #[TestWith(['session_token=a=b', ['session_token' => 'a=b']])]
    #[TestWith(['session_token=old; session_token=new', ['session_token' => 'new']])]
    public function cookies_parses_known_names_from_the_cookie_header(?string $header, array $expected): void
    {
        $request = self::request($header === null ? [] : ['HTTP_COOKIE' => $header]);

        $this->assertSame($expected, $request->cookies);
        $this->assertSame($expected, $request->cookies); // memoised read is stable
    }

    /**
     * @param list<mixed> $args
     */
    #[Test]
    #[TestWith(['addHeader', [HttpHeaders::Cookie, 'session_token=evil']])]
    #[TestWith(['rmHeader', [HttpHeaders::Cookie]])]
    public function refuses_to_modify_the_cookie_header(string $method, array $args): void
    {
        $request = self::request(['HTTP_COOKIE' => 'session_token=abc']);

        try {
            $request->$method(...$args);
            $this->fail('Expected WebError');
        } catch (WebError) {
        }

        $this->assertSame([HttpCookies::SessionToken->value => 'abc'], $request->cookies);
    }
}
