<?php

declare(strict_types=1);

namespace Vwork\Domain;

use Closure;
use Override;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;
use Vwork\Shared\Collections\Registry;

/**
 * Shared lazy-build-and-cache mechanics for every domain registry.
 *
 * Subclasses just supply the class-string => closure bindings; this class
 * makes sure each closure runs at most once and every later caller gets
 * back the same instance instead of a fresh one.
 *
 * This is to be extended by other classes (eg: AppRegistry)
 *
 * @template-covariant T of IDomainRegistry - the interface bindings are written against (eg: IAppRegistry)
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class DomainRegistry implements IDomainRegistry
{
    /** @var Registry<T> */
    protected readonly Registry $registry;

    /**
     * @param array<class-string, array<class-string, Closure(T): object>> $bindings
     * @param list<class-string> $allowedCategories - additional categories a subclass needs (eg: AppRegistry adding IController / IMiddleware)
     * @param T $registrar - the owner, passed to every factory
     */
    public function __construct(array $bindings, array $allowedCategories, object $registrar)
    {
        $this->registry = new Registry(
            $bindings,
            [IFacade::class, IInfrastructure::class, ...$allowedCategories],
            $registrar
        );
    }

    #[Override]
    public function getInfrastructure(string $name): IInfrastructure
    {
        return $this->registry->resolve(IInfrastructure::class, $name);
    }

    #[Override]
    public function getFacade(string $name): IFacade
    {
        return $this->registry->resolve(IFacade::class, $name);
    }
}
