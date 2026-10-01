<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Override;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Middleware\MiddlewareBase;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Pipeline\PipelineContext;

/**
 * Opens MiddlewareBase's protected deny() to tests.
 */
final readonly class MiddlewareBaseStub extends MiddlewareBase
{
    public function callDeny(Request $request, string $message, HttpStatus $status): Response
    {
        return $this->deny($request, $message, $status);
    }

    // IMiddleware: always denies, so the chain never continues
    #[Override]
    public function handle(Request $request, array $attr, IPipelineHandler $next, PipelineContext $ctx): Response
    {
        return $this->deny($request, 'stub', HttpStatus::Forbidden);
    }
}
