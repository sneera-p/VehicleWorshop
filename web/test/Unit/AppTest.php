<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vwork\Web\App;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Pipeline\ControllerHandler;
use Vwork\Web\Router\Router;
use Vwork\Web\Test\Stubs\ControllerStub;

final class AppTest extends TestCase
{
    /** A Request for $method $path, without touching the superglobals. */
    private static function request(HttpMethods $method, string $path): Request
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        new ReflectionClass(Request::class)->getProperty('method')->setValue($request, $method);
        new ReflectionClass(Request::class)->getProperty('path')->setValue($request, $path);

        return $request;
    }

    private static function app(): App
    {
        $router = new Router();
        $router->register(HttpMethods::GET, '/jobs', new ControllerHandler(new ControllerStub(), 'index'));

        return new App($router);
    }

    #[Test]
    public function handle_request_runs_the_matched_pipeline(): void
    {
        $response = self::app()->handleRequest(self::request(HttpMethods::GET, '/jobs'));

        $this->expectOutputString('index');
        $response->send();
    }

    #[Test]
    public function handle_request_answers_405_with_allow_when_the_method_is_wrong(): void
    {
        $response = self::app()->handleRequest(self::request(HttpMethods::POST, '/jobs'));

        $this->assertSame(HttpStatus::MethodNotAllowed, $response->status);
        $this->assertSame(['GET'], $response->headers->list['Allow']);
    }

    #[Test]
    public function handle_request_answers_404_for_an_unknown_path(): void
    {
        $response = self::app()->handleRequest(self::request(HttpMethods::GET, '/nope'));

        $this->assertSame(HttpStatus::NotFound, $response->status);
    }
}
