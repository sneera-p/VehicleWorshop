<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Override;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Pipeline\PipelineContext;

/** Lets every request through. */
final class MiddlewareStub implements IMiddleware
{
    #[Override]
    public function handle(Request $request, array $attr, IPipelineHandler $next, PipelineContext $ctx): Response
    {
        return $next->handle($request, $attr);
    }
}
