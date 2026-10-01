<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Pipeline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vwork\Web\Controllers\ControllerError;
use Vwork\Web\Http\Request;
use Vwork\Web\Pipeline\ControllerHandler;
use Vwork\Web\Test\Stubs\ControllerStub;

final class ControllerHandlerTest extends TestCase
{
    private static function controller(): ControllerStub
    {
        return new ControllerStub();
    }

    #[Test]
    public function handle_returns_the_controller_response(): void
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();

        $response = new ControllerHandler($this->controller(), 'index')->handle($request, []);

        $this->expectOutputString('index');
        $response->send(true);
    }

    #[Test]
    public function refuses_a_method_that_is_not_an_action(): void
    {
        $this->expectException(ControllerError::class);
        new ControllerHandler($this->controller(), 'notMarked');
    }
}
