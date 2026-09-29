<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Pipeline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vwork\Web\Http\Request;
use Vwork\Web\Pipeline\ControllerHandler;
use Vwork\Web\Pipeline\MiddlewareHandler;
use Vwork\Web\Pipeline\PipelineContext;
use Vwork\Web\Test\Stubs\ControllerStub;
use Vwork\Web\Test\Stubs\MiddlewareStub;

final class MiddlewareHandlerTest extends TestCase
{
    #[Test]
    public function handle_passes_the_request_on_to_next(): void
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        $next = new ControllerHandler(new ControllerStub(), 'index');

        $response = new MiddlewareHandler(new MiddlewareStub(), $next, new PipelineContext(roles: 'admin'))
            ->handle($request, []);

        $this->expectOutputString('index');
        $response->send();
    }
}
