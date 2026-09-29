<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vwork\Shared\Exception\VworkError;
use Vwork\Web\AppBuilder;
use Vwork\Web\IAppBuilder;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Http\Request;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Test\Stubs\ControllerStub;
use Vwork\Web\Test\Stubs\MiddlewareStub;
use Vwork\Web\WebError;

/**
 * @phpstan-import-type RouteConfig from IAppBuilder
 */
final class AppBuilderTest extends TestCase
{
    /**
     * @param list<class-string<IMiddleware>> $middleware
     * @return RouteConfig
     */
    private static function route(string $path, array $middleware = []): array
    {
        return [
            'method' => HttpMethods::GET,
            'path' => $path,
            'controller' => ['class' => ControllerStub::class, 'method' => 'index'],
            'middleware' => $middleware,
            'context' => ['roles' => 'admin'],
        ];
    }

    #[Test]
    public function build_wires_services_and_routes_into_a_working_app(): void
    {
        $builder = new AppBuilder();
        $builder->addControllers([ControllerStub::class => static fn () => new ControllerStub()]);
        $builder->addMiddleware([MiddlewareStub::class => static fn () => new MiddlewareStub()]);
        $builder->addRoutes([self::route('/jobs', [MiddlewareStub::class])]);

        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        new ReflectionClass(Request::class)->getProperty('method')->setValue($request, HttpMethods::GET);
        new ReflectionClass(Request::class)->getProperty('path')->setValue($request, '/jobs');

        $response = $builder->build()->handleRequest($request);

        $this->expectOutputString('index');
        $response->send();
    }

    #[Test]
    public function add_throws_when_a_class_is_registered_twice(): void
    {
        $builder = new AppBuilder();
        $builder->addControllers([ControllerStub::class => static fn () => new ControllerStub()]);

        $this->expectException(WebError::class);
        $builder->addControllers([ControllerStub::class => static fn () => new ControllerStub()]);
    }

    #[Test]
    public function build_throws_for_a_route_whose_controller_is_not_registered(): void
    {
        $builder = new AppBuilder();
        $builder->addRoutes([self::route('/jobs')]);

        $this->expectException(VworkError::class);
        $builder->build();
    }
}
