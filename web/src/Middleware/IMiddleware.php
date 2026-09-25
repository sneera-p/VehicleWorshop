<?php

declare(strict_types=1);

namespace Vwork\Web\Middleware;

use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Pipeline\PipelineContext;

/**
 * A guard that stands in front of a controller.
 *
 * It looks at the request and decides: call $next->handle() to let it
 * through, or return its own Response to stop it here.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IMiddleware
{
    /**
     * @param array<string, mixed> $attr values from the route and earlier stops
     */
    public function handle(Request $request, array $attr, IPipelineHandler $next, PipelineContext $ctx): Response;
}
