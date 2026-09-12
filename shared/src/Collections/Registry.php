<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Closure;
use Vwork\Shared\Exception\VworkError;

/**
 * lazy-build-and-cache registry.
 *
 * Subclasses just supply the class-string => closure bindings;
 * this class makes sure each closure runs at most once and every later caller gets the same instance.
 *
 * This can be extended by other classes (eg: AppServiceRegistry) or used just as it is
 *
 * @template T of object -  the registry interface bindings are written against (eg: IDomainRegistry).
 *                          Subclasses prove they satisfy T via $registrar, since PHP/PHPStan can't
 *                          infer "$this implements T" on its own for a self-referential generic.
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class Registry
{
    /**
     * @var array<class-string, array<class-string, object>> $resolved
     */
    protected array $resolved = [];

    /**
     * @param array<class-string, array<class-string, Closure(T): object>> $bindings
     * @param list<class-string> $allowedCategories
     */
    public function __construct(
        protected readonly array $bindings,
        protected readonly array $allowedCategories
    ) {
        $unknown = array_diff(array_keys($bindings), $allowedCategories);
        if ($unknown !== []) {
            throw new VworkError(static::class . ' bindings may only be categorized by ' . implode(', ', $allowedCategories));
        }
    }

    /**
     * @param class-string $category
     * @param class-string $key
     * @param T $registrar - $this (child class object) narrowed to the interface bound to T
     *
     * @throws VworkError - if there is no registered service
     */
    protected function resolve(string $category, string $key, mixed $registrar): object
    {
        if (!in_array($category, $this->allowedCategories, strict: true)) {
            throw new VworkError("$category not allowed");
        }

        $cached = $this->resolved[$category][$key] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $factory = $this->bindings[$category][$key] ?? null;
        if ($factory !== null) {
            $instance = $factory($registrar);
            $this->resolved[$category][$key] = $instance;
            return $instance;
        }

        throw new VworkError("No binding registered for {$key}");
    }
}
