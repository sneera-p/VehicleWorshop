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
        $data = [
            'title' => 'Dummy Page',
            'message' => 'Hello Sailor!'
        ];
        return $this->view($req, 'dummy/dummy', $data, 'layouts/blank');
    }

    /** @param StringMap $attr */
    #[ControllerAction]
    public function dummyhello(Request $req, array $attr): Response
    {
        $data = [
            'message' => 'Hello Sailor!'
        ];
        return $this->view($req, 'dummy/hello', $data);
    }

    /** @param StringMap $attr */
    #[ControllerAction]
    public function greeting(Request $req, array $attr): Response
    {
        $render = fn (string $message) => $this->view->render('dummy/greeting', [ 'message' => $message ]);
        return self::sse(static function (Closure $emit) use ($render): void {
            while (connection_aborted() === 0) {
                $token = bin2hex(random_bytes(8));
                $emit(
                    'dummy:event',
                    $render($token)
                );
                sleep(2);
            }
        });
    }
}
