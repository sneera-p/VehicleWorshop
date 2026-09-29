<?php

declare(strict_types=1);

namespace Vwork\Web\Registry;

use Closure;
use Override;
use Vwork\Domain\DomainRegistry;
use Vwork\Domain\IDomainRegistry;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;

/**
 * The one door into domain/, controllers and middleware.
 *
 * It is the domain registry plus two more shelves: one for controllers,
 * one for middleware. Each is built the first time someone asks, then
 * the same object is handed out every time after.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class AppServiceRegistry extends DomainRegistry implements IAppServiceRegistry
{
    /**
     * @param array<class-string, array<class-string, Closure(IDomainRegistry): object>> $bindings
     */
    public function __construct(array $bindings)
    {
        parent::__construct(
            $bindings,
            [IController::class, IMiddleware::class]
        );
    }

    #[Override]
    public function getController(string $name): IController
    {
        /** @var IController */
        return $this->resolve(IController::class, $name, $this);
    }

    #[Override]
    public function getMiddleware(string $name): IMiddleware
    {
        /** @var IMiddleware */
        return $this->resolve(IMiddleware::class, $name, $this);
    }
}
