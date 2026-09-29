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
 * This can be extended by other classes (eg: AppServiceRegistry) or used just as it is
 *
 * @extends Registry<IDomainRegistry>
 * @author Senira <senirahan@gmail.com>
 */
class DomainRegistry extends Registry implements IDomainRegistry
{
    /**
     * @param array<class-string, array<class-string, Closure(IDomainRegistry): object>> $bindings
     * @param list<class-string> $allowedCategories - additional categories a subclass needs
     *                                                (eg: AppServiceRegistry adding IController / IMiddleware)
     */
    public function __construct(array $bindings, array $allowedCategories = [])
    {
        parent::__construct(
            $bindings,
            [IFacade::class, IInfrastructure::class, ...$allowedCategories]
        );
    }

    #[Override]
    public function getInfrastructure(string $name): IInfrastructure
    {
        /** @var IInfrastructure */
        return $this->resolve(IInfrastructure::class, $name, $this);
    }

    #[Override]
    public function getFacade(string $name): IFacade
    {
        /** @var IFacade */
        return $this->resolve(IFacade::class, $name, $this);
    }
}
