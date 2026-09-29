<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Override;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Registry\IHttpRegistry;

/**
 * Builds whatever class it is asked for. No config, no caching.
 */
final class HttpRegistryStub implements IHttpRegistry
{
    #[Override]
    public function getController(string $name): IController
    {
        return new $name();
    }

    #[Override]
    public function getMiddleware(string $name): IMiddleware
    {
        return new $name();
    }
}
