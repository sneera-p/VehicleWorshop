<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Closure;
use Vwork\Shared\Collections\IRegistry;
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
final readonly class DummyController extends ControllerBase
{
    public function __construct(IRegistry $registry)
    {
        parent::__construct($registry);
    }

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
        return $this->view($req, 'dummy', [ 'title' => 'Dummy Page', 'message' => 'Hello Sailor!' ], 'layouts/main');
    }

    /** @param StringMap $attr */
    #[ControllerAction]
    public function greeting(Request $req, array $attr): Response
    {
        return self::sse(static function (Closure $emit): void {
            while (connection_aborted() === 0) {
                $token = bin2hex(random_bytes(8));
                $emit('dummy', ['message' => $token]);
                sleep(2);
            }
        });
    }
}
