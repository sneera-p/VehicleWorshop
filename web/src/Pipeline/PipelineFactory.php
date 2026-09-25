<?php

declare(strict_types=1);

namespace Vwork\Web\Pipeline;

use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Registry\IHttpRegistry;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;

/**
 * Builds the chain of stops for one route.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class PipelineFactory
{
    public function __construct(
        public IHttpRegistry $registry,
    ) {
    }

    /**
     * We build from the inside out. The controller is made first. Then
     * each middleware, last one first, is wrapped around what we have.
     * So for [Auth, Rbac] the request walks: Auth → Rbac → controller.
     *
     * @param array{
     *  controller: array{
     *    class: class-string<IController>,
     *    method: string
     *  },
     *  middleware: list<class-string<IMiddleware>>,
     *  context: array<string, mixed>
     * } $config
     */
    public function build(array $config): IPipelineHandler
    {
        $cur = new ControllerHandler(
            $this->registry->getController($config['controller']['class']),
            $config['controller']['method']
        );

        foreach (array_reverse($config['middleware']) as $middleware) {
            $ctx ??= PipelineContext::fromArray($config['context']);
            $cur = new MiddlewareHandler($this->registry->getMiddleware($middleware), $cur, $ctx);
        }

        return $cur;
    }
}
