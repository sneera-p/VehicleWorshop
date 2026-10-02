<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Vwork\Shared\Exception\VworkError;

/**
 * Hands out services by the class or interface they were bound under.
 *
 * The only thing config closures see: they receive an IRegistry and pull
 * their dependencies from it, never the concrete registry. Everything else
 * gets its dependencies through its constructor; nothing outside boot code
 * and config closures should hold a registry.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IRegistry
{
    /**
     * @template T of object
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if nothing is bound to $name
     *
     * ```php
     *      $cache = $registry->get(ICache::class); // typed ICache
     * ```
     */
    public function get(string $name): object;
}
