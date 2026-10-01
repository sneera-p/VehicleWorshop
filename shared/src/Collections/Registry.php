<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Closure;
use Vwork\Shared\Exception\VworkError;

/**
 * A lazy-build-and-cache registry, meant to be owned, not extended.
 *
 * The owner (eg: DomainRegistry) hands over its class-string => closure
 * bindings and itself as the $registrar. Each closure runs at most once,
 * receives the registrar so it can resolve its own dependencies, and
 * every later caller gets the same instance.
 *
 * Not readonly on purpose: $resolved fills up as services are first used.
 * That state is the same for every request, so it's safe in worker mode.
 *
 * @template-covariant T of object - the interface bindings are written against (eg: IAppRegistry)
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Registry
{
    /** @var array<class-string, array<class-string, object>> */
    private array $resolved = [];

    /** @var array<class-string, array<class-string, bool>> */
    private array $building = [];

    /**
     * @param array<class-string, array<class-string, Closure(T): object>> $bindings
     * @param list<class-string> $allowedCategories
     * @param T $registrar - the owner, passed to every factory
     */
    public function __construct(
        private readonly array $bindings,
        private readonly array $allowedCategories,
        private readonly object $registrar,
    ) {
        $unknown = array_diff(array_keys($bindings), $allowedCategories);
        if ($unknown !== []) {
            throw new VworkError('Bindings may only be categorized by ' . implode(', ', $allowedCategories));
        }
    }

    /**
     * @template S of object
     * @param class-string $category
     * @param class-string<S> $key
     * @return S
     *
     * @throws VworkError if the category isn't allowed, nothing is bound to $key,
     *     the factory builds the wrong type, or the bindings form a cycle
     */
    public function resolve(string $category, string $key): object
    {
        if (!in_array($category, $this->allowedCategories, strict: true)) {
            throw new VworkError("{$category} not allowed");
        }

        $cached = $this->resolved[$category][$key] ?? null;
        if ($cached instanceof $key) {
            return $cached;
        }

        $factory = $this->bindings[$category][$key]
            ?? throw new VworkError("No binding registered for {$category} {$key}");

        if (isset($this->building[$category][$key])) {
            throw new VworkError("Circular binding: {$category} {$key}");
        }

        $this->building[$category][$key] = true;
        try {
            $instance = $factory($this->registrar);
        } finally {
            unset($this->building[$category][$key]);
        }

        if (!$instance instanceof $key) {
            throw new VworkError("Binding for {$key} built a " . $instance::class);
        }

        return $this->resolved[$category][$key] = $instance;
    }
}
