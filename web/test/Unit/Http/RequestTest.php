<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\HttpHeaders;
use Vwork\Web\Http\HttpCookies;
use Vwork\Web\Http\Request;
use Vwork\Web\Test\Mocks\MockInputStream;
use Vwork\Web\WebException;

final class RequestTest extends TestCase
{
    /**
     * @var list<array<mixed, mixed>>
     */
    private array $globalState;

    #[Override]
    protected function setUp(): void
    {
        $this->globalState = [$_SERVER, $_GET, $_POST, $_FILES];
        parent::setUp();
        $_SERVER['REQUEST_METHOD'] = HttpMethods::GET->value;
        $_SERVER['REMOTE_ADDR'] = '';
        $_SERVER['REQUEST_URI'] = '';
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        MockInputStream::restore();
        [$_SERVER, $_GET, $_POST, $_FILES] = $this->globalState;
    }

    #[Test]
    #[TestWith(['Hello raaw Body'])]
    #[TestWith(['Bye ladies'])]
    #[TestWith(['Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras feugiat mauris enim, non auctor tellus malesuada quis. Nulla facilisi. Vivamus non consectetur odio. Curabitur vel volutpat odio, id pulvinar dui. Sed sit amet pellentesque sapien, sit amet tincidunt nunc. Morbi vehicula urna sapien, at gravida mauris elementum vitae. In hac habitasse platea dictumst. Vivamus tristique nisl sed nisl fermentum, vitae fermentum mi finibus. Proin varius libero leo, eu maximus libero sollicitudin sed. Vestibulum eu pharetra tortor. Suspendisse augue leo, tempus sit amet metus ultrices, mattis condimentum ante. Aenean a libero eros. Curabitur dui arcu, ultricies in nisi eget, dignissim eleifend urna. Suspendisse et euismod velit, nec placerat enim. Suspendisse dui quam, dictum eu nisl a, faucibus bibendum velit.'])]
    public function from_globals_captures_the_raw_body(string $expected): void
    {
        MockInputStream::install($expected);

        $request = Request::fromGlobals();
        $this->assertSame($expected, $request->body);
    }

    #[Test]
    public function from_globals_resolves_a_valid_method(): void
    {
        MockInputStream::install('');
        $_SERVER['REQUEST_METHOD'] = HttpMethods::POST->value;

        $request = Request::fromGlobals();

        $this->assertSame(HttpMethods::POST, $request->method);
    }

    #[Test]
    public function from_globals_uppercases_a_lowercase_method(): void
    {
        MockInputStream::install('');
        $_SERVER['REQUEST_METHOD'] = 'get';

        $request = Request::fromGlobals();

        $this->assertSame(HttpMethods::GET, $request->method);
    }

    #[Test]
    public function from_globals_throws_on_an_unsupported_method(): void
    {
        MockInputStream::install('');
        $_SERVER['REQUEST_METHOD'] = 'BREW';

        $this->expectException(WebException::class);
        Request::fromGlobals();
    }

    #[Test]
    public function from_globals_strips_the_query_string_from_the_path(): void
    {
        MockInputStream::install('');
        $_SERVER['REQUEST_URI'] = '/jobs/42?tab=notes&x=1';

        $request = Request::fromGlobals();

        $this->assertSame('/jobs/42', $request->path);
    }

    #[Test]
    public function from_globals_leaves_the_path_unchanged_when_there_is_no_query_string(): void
    {
        MockInputStream::install('');
        $_SERVER['REQUEST_URI'] = '/jobs/42';

        $request = Request::fromGlobals();

        $this->assertSame('/jobs/42', $request->path);
    }

    #[Test]
    public function from_globals_passes_get_through_as_query(): void
    {
        MockInputStream::install('');
        $_GET = ['tab' => 'notes', 'x' => '1'];

        $request = Request::fromGlobals();

        $this->assertSame(['tab' => 'notes', 'x' => '1'], $request->query);
    }

    #[Test]
    public function from_globals_maps_a_known_http_header(): void
    {
        MockInputStream::install('');
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $request = Request::fromGlobals();

        $this->assertSame(['application/json'], $request->headers[HttpHeaders::ContentType->value]);
        $this->assertSame(['phpunit'], $request->headers[HttpHeaders::UserAgent->value]);
    }

    #[Test]
    public function from_globals_drops_a_server_key_that_isnt_a_known_header(): void
    {
        MockInputStream::install('');
        $_SERVER['HTTP_X_MADE_UP'] = 'whatever';

        $request = Request::fromGlobals();

        $this->assertSame([], $request->headers);
    }

    #[Test]
    public function from_globals_headers_is_empty_when_no_known_header_keys_are_present(): void
    {
        MockInputStream::install('');

        $request = Request::fromGlobals();

        $this->assertSame([], $request->headers);
    }

    #[Test]
    public function from_globals_flattens_a_single_file_upload(): void
    {
        MockInputStream::install('');
        $_FILES = [
            'photo' => [
                'name' => 'damage.jpg',
                'tmp_name' => '/tmp/php1',
                'type' => 'image/jpeg',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
            ],
        ];

        $request = Request::fromGlobals();

        $this->assertArrayHasKey('photo', $request->files);
        $this->assertSame('damage.jpg', $request->files['photo']->name);
    }

    #[Test]
    public function from_globals_flattens_a_multi_file_upload_one_per_index(): void
    {
        MockInputStream::install('');
        $_FILES = [
            'photos' => [
                'name' => ['a.jpg', 'b.jpg'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'type' => ['image/jpeg', 'image/jpeg'],
                'size' => [100, 200],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            ],
        ];

        $request = Request::fromGlobals();

        $this->assertArrayHasKey('photos[0]', $request->files);
        $this->assertArrayHasKey('photos[1]', $request->files);
        $this->assertSame('a.jpg', $request->files['photos[0]']->name);
        $this->assertSame('b.jpg', $request->files['photos[1]']->name);
    }

    #[Test]
    public function cookies_parses_the_cookie_header(): void
    {
        MockInputStream::install('');
        $_SERVER['HTTP_COOKIE'] = 'session_token=abc; csrf_token=xyz';

        $request = Request::fromGlobals();

        $this->assertSame(
            [
                HttpCookies::SessionToken->value => 'abc',
                HttpCookies::CsrfToken->value => 'xyz',
            ],
            $request->cookies,
        );
    }

    #[Test]
    public function cookies_drops_an_unknown_cookie_name(): void
    {
        MockInputStream::install('');
        $_SERVER['HTTP_COOKIE'] = 'session_token=abc; ga_tracking=xyz';

        $request = Request::fromGlobals();

        $this->assertSame([HttpCookies::SessionToken->value => 'abc'], $request->cookies);
    }

    #[Test]
    public function cookies_is_empty_when_no_cookie_header_was_sent(): void
    {
        MockInputStream::install('');

        $request = Request::fromGlobals();

        $this->assertSame([], $request->cookies);
    }

    #[Test]
    public function from_globals_captures_the_remote_ip(): void
    {
        MockInputStream::install('');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

        $request = Request::fromGlobals();

        $this->assertSame('203.0.113.7', $request->ip);
    }

    #[Test]
    public function from_globals_passes_post_through_as_form_data(): void
    {
        MockInputStream::install('');
        $_POST = ['name' => 'Oil change', 'urgent' => 'true'];

        $request = Request::fromGlobals();

        $this->assertSame(['name' => 'Oil change', 'urgent' => 'true'], $request->formData);
    }
}
