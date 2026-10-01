<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Stubs;

use Closure;
use Vwork\Shared\Collections\Registry;

/**
 * The smallest owner: holds a Registry and hands itself to it, the same
 * shape as DomainRegistry. Factories resolve their dependencies via get().
 */
final class RegistryOwnerStub
{
    /** @var Registry<self> */
    public readonly Registry $registry;

    /**
     * @param array<class-string, array<class-string, Closure(self): object>> $bindings
     * @param list<class-string> $allowedCategories
     */
    public function __construct(array $bindings, array $allowedCategories)
    {
        $this->registry = new Registry($bindings, $allowedCategories, $this);
    }

    /**
     * @template S of object
     * @param class-string $category
     * @param class-string<S> $key
     * @return S
     */
    public function get(string $category, string $key): object
    {
        return $this->registry->resolve($category, $key);
    }
}
