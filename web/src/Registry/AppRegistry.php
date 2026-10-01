<?php

declare(strict_types=1);

namespace Vwork\Web\Registry;

use Closure;
use Override;
use Vwork\Domain\DomainRegistry;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Utils\IUtility;

/**
 * The one door into domain/, controllers and middleware.
 *
 * It is the domain registry plus two more shelves: one for controllers,
 * one for middleware. Each is built the first time someone asks, then
 * the same object is handed out every time after.
 *
 * @extends DomainRegistry<IAppRegistry>
 *
 * @author Senira <senirahan@gmail.com>
 */
final class AppRegistry extends DomainRegistry implements IAppRegistry
{
    /**
     * @param array<class-string, array<class-string, Closure(IAppRegistry): object>> $bindings
     */
    public function __construct(array $bindings)
    {
        parent::__construct(
            $bindings,
            [IUtility::class, IController::class, IMiddleware::class],
            $this
        );
    }

    #[Override]
    public function getController(string $name): IController
    {
        return $this->registry->resolve(IController::class, $name);
    }

    #[Override]
    public function getMiddleware(string $name): IMiddleware
    {
        return $this->registry->resolve(IMiddleware::class, $name);
    }

    #[Override]
    public function getUtility(string $name): IUtility
    {
        return $this->registry->resolve(IUtility::class, $name);
    }
}
