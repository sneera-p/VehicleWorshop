<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Pipeline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\ControllerHandler;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Pipeline\MiddlewareHandler;
use Vwork\Web\Pipeline\PipelineFactory;
use Vwork\Web\Test\Stubs\ControllerStub;
use Vwork\Web\Test\Stubs\HttpRegistryStub;
use Vwork\Web\Test\Stubs\MiddlewareStub;
use Vwork\Web\WebError;

final class PipelineFactoryTest extends TestCase
{
    /**
     * @param list<class-string<IMiddleware>> $middleware
     * @param array<string, mixed> $context
     */
    private static function build(array $middleware, array $context): IPipelineHandler
    {
        return new PipelineFactory(new HttpRegistryStub())->build([
            'method' => HttpMethods::GET,
            'path' => '/',
            'controller' => ['class' => ControllerStub::class, 'method' => 'index'],
            'middleware' => $middleware,
            'context' => $context,
        ]);
    }

    #[Test]
    public function build_without_middleware_returns_the_controller(): void
    {
        $pipeline = self::build([], []); // a public route needs no context

        $this->assertInstanceOf(ControllerHandler::class, $pipeline);
    }

    #[Test]
    public function build_wraps_each_middleware_around_the_controller_with_one_context(): void
    {
        $first = self::build([MiddlewareStub::class, MiddlewareStub::class], ['roles' => 'admin']);

        $this->assertInstanceOf(MiddlewareHandler::class, $first);
        $second = $first->next;
        $this->assertInstanceOf(MiddlewareHandler::class, $second);
        $this->assertInstanceOf(ControllerHandler::class, $second->next);

        $this->assertSame('admin', $first->ctx->roles);
        $this->assertSame($first->ctx, $second->ctx);
    }

    #[Test]
    public function build_throws_for_a_bad_context(): void
    {
        $this->expectException(WebError::class);
        self::build([MiddlewareStub::class], []);
    }
}
