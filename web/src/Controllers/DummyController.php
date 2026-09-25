<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * A sample controller: proves a request can travel from the router,
 * through the pipeline, to an action and back.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class DummyController extends ControllerBase
{
    /** @param array<string, mixed> $attr */
    #[ControllerAction]
    public function hello(Request $req, array $attr): Response
    {
        return Response::text('Hello');
    }
}
