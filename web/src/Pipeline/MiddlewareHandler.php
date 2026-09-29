<?php

declare(strict_types=1);

namespace Vwork\Web\Pipeline;

use Override;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * A middle stop. It holds one middleware and the stop after it.
 *
 * The middleware decides: pass the request on to $next, or stop here
 * and answer itself (for example, 401 when nobody is logged in).
 *
 * @author Senira <senirahan@gmail.com>
 */
final class MiddlewareHandler implements IPipelineHandler
{
    public function __construct(
        public readonly IMiddleware $middleware,
        public readonly IPipelineHandler $next,
        public readonly PipelineContext $ctx
    ) {
    }

    /**
     * @param array<string, mixed> $attr
     */
    #[Override]
    public function handle(Request $request, array $attr): Response
    {
        return $this->middleware->handle($request, $attr, $this->next, $this->ctx);
    }
}
