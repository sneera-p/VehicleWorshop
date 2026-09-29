<?php

declare(strict_types=1);

namespace Vwork\Web\Pipeline;

use Override;
use Vwork\Web\Controllers\ControllerAction;
use Vwork\Web\Controllers\ControllerError;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * The last stop. Every middleware has said yes, so now we call one
 * method on one controller and return what it gives back.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class ControllerHandler implements IPipelineHandler
{
    /**
     * Pipelines are built once, at boot. A typo in the route config
     * (a missing or non-public method) fails here, not when a user
     * finally visits that page.
     *
     * @throws ControllerError if $method can't be called from outside
     */
    public function __construct(
        public readonly IController $controller,
        public readonly string $method,
    ) {
        ControllerAction::verify($controller, $method);
    }

    /**
     * @param array<string, mixed> $attr
     * @throws ControllerError if the method forgets to return a Response
     */
    #[Override]
    public function handle(Request $request, array $attr): Response
    {
        /** @var Response */
        return $this->controller->{$this->method}($request, $attr);
    }
}
