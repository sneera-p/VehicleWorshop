<?php

declare(strict_types=1);

namespace Vwork\Domain;

use Vwork\Shared\Exception\VworkError;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;

/**
 * The one door into domain/ — hands out facades and infrastructure by interface,
 * building each lazily on first request and reusing the same instance after.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IDomainRegistry
{
    /**
     * @template T of IInfrastructure
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if nothing is bound to $name
     *
     * ```php
     *      $cache = $registry->getInfrastructure(ICache::class); // typed ICache
     * ```
     */
    public function getInfrastructure(string $name): IInfrastructure;

    /**
     * @template T of IFacade
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if nothing is bound to $name
     *
     * ```php
     *      $jobs = $registry->getFacade(IJobFacade::class); // typed IJobFacade
     * ```
     */
    public function getFacade(string $name): IFacade;
}
