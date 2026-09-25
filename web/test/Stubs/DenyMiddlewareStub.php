<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Override;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Pipeline\PipelineContext;

/**
 * Stops every request with 401 and never calls $next.
 * It writes down what it was given in $got, so a test can check it.
 */
final class DenyMiddlewareStub implements IMiddleware
{
    /** @var array{Request, array<string, mixed>, IPipelineHandler, PipelineContext}|null */
    public ?array $got = null;

    #[Override]
    public function handle(Request $request, array $attr, IPipelineHandler $next, PipelineContext $ctx): Response
    {
        $this->got = [$request, $attr, $next, $ctx];
        return Response::error(HttpStatus::Unauthorized);
    }
}
