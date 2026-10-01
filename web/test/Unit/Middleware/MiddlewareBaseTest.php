<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Middleware;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Test\Stubs\MiddlewareBaseStub;
use Vwork\Web\Utils\View;

final class MiddlewareBaseTest extends TestCase
{
    /** @var array<mixed, mixed> */
    private array $server;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    private static function request(string $method, string $uri): Request
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => ''];
        return Request::fromGlobals();
    }

    private static function stub(): MiddlewareBaseStub
    {
        // layout/error.php here prints "title|path|message"
        return new MiddlewareBaseStub(new View('web/test/Fixtures/Views'));
    }

    #[Test]
    #[TestWith([HttpStatus::Unauthorized])]
    #[TestWith([HttpStatus::Forbidden])]
    #[TestWith([HttpStatus::NotFound])]
    public function deny_answers_with_the_given_status_as_html(HttpStatus $status): void
    {
        $response = self::stub()->callDeny(self::request('GET', '/jobs'), 'nope', $status);

        $this->assertSame($status, $response->status);
        $this->assertSame(['Content-Type' => ['text/html; charset=utf-8']], $response->headers->list);
    }

    #[Test]
    #[TestWith(['GET', '/jobs/42', 'Not yours', HttpStatus::Forbidden])]
    #[TestWith(['POST', '/auth/login?next=/jobs', 'Bad token', HttpStatus::Forbidden])] // query dropped
    #[TestWith(['DELETE', '/', '', HttpStatus::Unauthorized])]
    public function deny_renders_the_error_view_with_status_path_and_message(
        string $method,
        string $uri,
        string $message,
        HttpStatus $status,
    ): void {
        $response = self::stub()->callDeny(self::request($method, $uri), $message, $status);
        $path = explode('?', $uri, 2)[0];

        $this->expectOutputString("{$status->value} {$status->description()}|{$method} {$path}|{$message}");
        $response->send(true);
    }

    #[Test]
    public function deny_leaves_no_cookies_behind(): void
    {
        $response = self::stub()->callDeny(self::request('GET', '/'), 'nope', HttpStatus::Forbidden);

        $this->assertSame([], $response->cookies->list);
    }
}
