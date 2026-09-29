<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Closure;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * A sample controller: proves a request can travel from the router,
 * through the pipeline, to an action and back.
 *
 * @phpstan-import-type StringMap from IController
 *
 * @author Senira <senirahan@gmail.com>
 */
final class DummyController extends ControllerBase
{
    /** @param StringMap $attr */
    #[ControllerAction]
    public function hello(Request $req, array $attr): Response
    {
        return Response::text('Hello');
    }

    /** @param StringMap $attr */
    #[ControllerAction]
    public function dummy(Request $req, array $attr): Response
    {
        return self::view('dummy', [ 'title' => 'Dummy Page', 'message' => 'Hello Sailor!' ], 'layouts/main');
    }

    /** @param StringMap $attr */
    #[ControllerAction]
    public function greeting(Request $req, array $attr): Response
    {
        return self::sse(static function (Closure $emit) {

            $count = 1;
            // @phpstan-ignore while.alwaysTrue
            while (true) {
                $emit('dummy', ['message' => 'Hello There! ~Obi-Wan Kenobi']);
                $count++;
                sleep(2);
            }
        });
    }
}
